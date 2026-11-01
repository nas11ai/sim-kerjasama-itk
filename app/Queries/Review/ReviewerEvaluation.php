<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Review\EvaluationFormData;
use App\Data\View\Review\EvaluationSubmissionData;
use App\Data\View\Submission\SubmissionAnswerData;
use App\Data\View\Submission\Wizard\WizardFieldData;
use App\Models\FieldType;
use App\Models\FormPhaseDetail;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldOption;
use App\Models\SubmissionReviewer;
use App\Queries\Submission\ReadableSubmissionAnswers;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * REVIEWER-03-03 — isi pengajuan dan semua form evaluasi aktif tahapnya bagi satu penugasan
 * reviewer. Penugasan itu masih berlaku dan milik pemanggil (BR-SM-07, BR-REV-10), dijaga
 * controller. Membaca saja: draft respons baru dibuat saat reviewer menyimpan.
 *
 * @phpstan-type Evaluation array{submission: EvaluationSubmissionData, answers: list<SubmissionAnswerData>, forms: list<EvaluationFormData>, activeFormId: int|null, evaluationSettled: bool}
 *
 * @method static Evaluation run(SubmissionReviewer $assignment, ?int $requestedFormId)
 */
class ReviewerEvaluation implements ChangesNoState
{
    use AsAction;

    private const CLOSED = ['submitted', 'locked'];

    /** @return Evaluation */
    public function handle(SubmissionReviewer $assignment, ?int $requestedFormId): array
    {
        $submission = $assignment->formSubmission;
        $forms = $this->forms($assignment, $submission->formPhaseDetail);

        return [
            'submission' => EvaluationSubmissionData::of($submission),
            'answers' => ReadableSubmissionAnswers::run($submission),
            'forms' => $forms,
            'activeFormId' => $this->activeFormId($forms, $requestedFormId),
            'evaluationSettled' => $assignment->hasSettledEvaluation(),
        ];
    }

    /** @return list<EvaluationFormData> */
    private function forms(SubmissionReviewer $assignment, ?FormPhaseDetail $detail): array
    {
        if ($detail === null) {
            return [];
        }

        $started = $assignment->reviewerFormAssignments()
            ->with('reviewFormResponse.reviewFormFieldResponses')
            ->get()
            ->keyBy('review_evaluation_form_id');

        return array_values($detail->activeReviewEvaluationForms()
            ->with(['reviewFormFields' => fn (Relation $fields) => $fields->orderBy('order')->with([
                'fieldType:id,name',
                'reviewFormFieldOptions' => fn (Relation $options) => $options->orderBy('order'),
            ])])
            ->get()
            ->map(fn (ReviewEvaluationForm $form): EvaluationFormData => $this->form($form, $started->get($form->id)))
            ->all());
    }

    private function form(ReviewEvaluationForm $form, ?ReviewerFormAssignment $started): EvaluationFormData
    {
        $response = $started?->reviewFormResponse;
        $state = $this->state($started);
        $answers = [];

        foreach ($response->reviewFormFieldResponses ?? [] as $answer) {
            $answers[$answer->review_form_field_id] = $answer->value;
        }

        return new EvaluationFormData(
            formId: $form->id,
            assignmentId: $started?->id,
            title: $form->title,
            description: $form->description,
            required: $form->is_required,
            state: $state,
            dueDate: $started?->due_date?->toISOString(),
            submittedAt: $response?->submitted_at?->toISOString(),
            finalNotes: $response?->final_notes,
            // Sisa Policy `evaluate` setelah penjagaan controller hanyalah tenggat, yang sudah
            // terbaca di `state` (BR-FE-17); terkirim dan terkunci tak bisa disunting (BR-REV-08).
            canEdit: in_array($state, ['not_started', 'draft'], true),
            fields: array_values($form->reviewFormFields
                ->map(fn (ReviewFormField $field): WizardFieldData => $this->field($field, $answers[$field->id] ?? null))
                ->all()),
        );
    }

    private function state(?ReviewerFormAssignment $started): string
    {
        if ($started === null) {
            return 'not_started';
        }

        $status = $started->reviewFormResponse?->status;

        if (in_array($status, self::CLOSED, true)) {
            return $status;
        }

        return $started->isClosedByDeadline() ? 'overdue' : 'draft';
    }

    private function field(ReviewFormField $field, ?string $value): WizardFieldData
    {
        return new WizardFieldData(
            id: $field->id,
            label: $field->label,
            type: $field->fieldType instanceof FieldType ? strtolower($field->fieldType->name) : 'text',
            required: $field->is_required,
            value: $value,
            // `value` boleh kosong (kolomnya untuk nilai yang beda dari label); jawaban lama
            // menyimpan label untuk opsi semacam itu, jadi label jadi nilainya.
            options: array_values($field->reviewFormFieldOptions
                ->map(fn (ReviewFormFieldOption $option): array => ['value' => $option->value ?? $option->label, 'label' => $option->label])
                ->all()),
            fileName: null,
            downloadUrl: null,
        );
    }

    /** @param  list<EvaluationFormData>  $forms */
    private function activeFormId(array $forms, ?int $requested): ?int
    {
        foreach ($forms as $form) {
            if ($form->formId === $requested) {
                return $requested;
            }
        }

        // Tanpa pilihan, reviewer dibawa ke pekerjaan berikutnya: form pertama yang belum dikirim.
        foreach ($forms as $form) {
            if (!in_array($form->state, self::CLOSED, true)) {
                return $form->formId;
            }
        }

        return $forms[0]->formId ?? null;
    }
}
