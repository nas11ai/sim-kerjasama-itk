<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormField;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lembar evaluasi yang sudah disubmit, berlabel versinya (BR-REV-18); siapa pembacanya urusan
 * pemanggil. Operator ikut membaca lembar reviewer yang digantikan (BR-REV-10, W22) dan lembar
 * riwayat versi lama (BR-REV-19); pembaca lain hanya yang berlaku (BR-REV-11/12, BR-MON-09).
 *
 * @phpstan-type Score array{reviewer: string, reviewer_type: string, submitted_at: string|null, replaced: bool, version_label: string, superseded: bool, answers: list<array{label: string, value: string|null}>}
 *
 * @method static list<Score> run(FormSubmission $submission, bool $includeReplaced)
 */
class SubmittedEvaluationSheets implements ChangesNoState
{
    use AsAction;

    /** @return list<Score> */
    public function handle(FormSubmission $submission, bool $includeReplaced): array
    {
        return array_values(SubmissionReviewer::query()
            ->where('form_submission_id', $submission->id)
            ->when(!$includeReplaced, fn ($q) => $q->where('status', 'active'))
            ->with([
                'reviewer.user:id,name',
                'reviewerFormAssignments.reviewFormResponse.reviewFormFieldResponses.reviewFormField:id,label',
                'reviewerFormAssignments.reviewFormResponse.reviewEvaluationFormVersion:id,number',
            ])
            ->when($includeReplaced, fn ($q) => $q->with([
                'reviewerFormAssignments.supersededResponses.reviewFormFieldResponses.reviewFormField:id,label',
                'reviewerFormAssignments.supersededResponses.reviewEvaluationFormVersion:id,number',
            ]))
            ->orderBy('id')
            ->get()
            ->flatMap(fn (SubmissionReviewer $assignment): array => $assignment->reviewerFormAssignments
                ->flatMap(fn (ReviewerFormAssignment $form): array => $this->scores($assignment, $form, $includeReplaced))
                ->all())
            ->all());
    }

    /**
     * Riwayat lebih dulu, lalu lembar yang berlaku — urutan waktu pengisiannya.
     *
     * @return list<Score>
     */
    private function scores(SubmissionReviewer $assignment, ReviewerFormAssignment $form, bool $includeReplaced): array
    {
        $responses = $includeReplaced ? [...$form->supersededResponses, $form->reviewFormResponse] : [$form->reviewFormResponse];
        $scores = [];

        foreach ($responses as $response) {
            // Evaluasi yang belum disubmit belum menjadi penilaian sama sekali.
            if ($response !== null && $response->status === 'submitted') {
                $scores[] = $this->score($assignment, $response);
            }
        }

        return $scores;
    }

    /** @return Score */
    private function score(SubmissionReviewer $assignment, ReviewFormResponse $response): array
    {
        return [
            'reviewer' => $assignment->reviewer->user->name ?? 'Penilai',
            'reviewer_type' => $assignment->reviewer->reviewer_type,
            'submitted_at' => $response->submitted_at?->toDateTimeString(),
            'replaced' => $assignment->status === 'replaced',
            'version_label' => $response->reviewEvaluationFormVersion->label(),
            'superseded' => $response->superseded_at !== null,
            'answers' => $this->answers($response),
        ];
    }

    /** @return list<array{label: string, value: string|null}> */
    private function answers(ReviewFormResponse $response): array
    {
        $answers = [];

        foreach ($response->reviewFormFieldResponses as $answer) {
            // Jawaban tanpa field-nya tidak punya label untuk dibaca; tidak ditampilkan.
            if ($answer->reviewFormField instanceof ReviewFormField) {
                $answers[] = ['label' => $answer->reviewFormField->label, 'value' => $answer->value];
            }
        }

        return $answers;
    }
}
