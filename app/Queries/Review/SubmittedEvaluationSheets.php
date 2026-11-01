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
 * Lembar evaluasi yang sudah disubmit, tanpa menimbang siapa pembacanya — itu urusan
 * pemanggil. Operator ikut membaca lembar reviewer yang digantikan (BR-REV-10, W22).
 *
 * @phpstan-type Score array{reviewer: string, reviewer_type: string, submitted_at: string|null, replaced: bool, answers: list<array{label: string, value: string|null}>}
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
            ])
            ->orderBy('id')
            ->get()
            ->flatMap(fn (SubmissionReviewer $assignment): array => $assignment->reviewerFormAssignments
                ->map(fn (ReviewerFormAssignment $form): ?array => $this->score($assignment, $form))
                ->filter()
                ->all())
            ->all());
    }

    /** @return Score|null */
    private function score(SubmissionReviewer $assignment, ReviewerFormAssignment $form): ?array
    {
        $response = $form->reviewFormResponse;

        // Evaluasi yang belum disubmit belum menjadi penilaian sama sekali.
        if ($response === null || $response->status !== 'submitted') {
            return null;
        }

        return [
            'reviewer' => $assignment->reviewer->user->name ?? 'Reviewer',
            'reviewer_type' => $assignment->reviewer->reviewer_type,
            'submitted_at' => $response->submitted_at?->toDateTimeString(),
            'replaced' => $assignment->status === 'replaced',
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
