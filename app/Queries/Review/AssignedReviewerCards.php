<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-10 — reviewer yang sedang bertugas atas pengajuan; yang sudah diganti tidak tampil.
 * `id` adalah id penugasan, bukan id reviewer.
 *
 * @phpstan-type ReviewerCard array{id: int, user: array{id: int|null, name: string|null, email: string|null}, reviewer_type: string}
 *
 * @method static list<ReviewerCard> run(FormSubmission $submission)
 */
class AssignedReviewerCards implements ChangesNoState
{
    use AsAction;

    /** @return list<ReviewerCard> */
    public function handle(FormSubmission $submission): array
    {
        return array_values($submission->activeSubmissionReviewers()
            ->with('reviewer.user:id,name,email')
            ->get()
            ->map(fn (SubmissionReviewer $assignment): array => [
                'id' => $assignment->id,
                'user' => [
                    'id' => $assignment->reviewer->user?->id,
                    'name' => $assignment->reviewer->user?->name,
                    'email' => $assignment->reviewer->user?->email,
                ],
                'reviewer_type' => $assignment->reviewer->reviewer_type,
            ])
            ->all());
    }
}
