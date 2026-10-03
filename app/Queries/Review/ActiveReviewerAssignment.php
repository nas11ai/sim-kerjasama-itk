<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-07, BR-REV-10 — penugasan reviewer yang masih berlaku atas satu pengajuan.
 * Record reviewer yang sudah diganti disimpan sebagai sejarah, bukan sebagai wewenang.
 *
 * @method static SubmissionReviewer|null run(FormSubmission $submission, int $reviewerId)
 */
class ActiveReviewerAssignment implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission, int $reviewerId): ?SubmissionReviewer
    {
        return SubmissionReviewer::query()
            ->where('form_submission_id', $submission->id)
            ->where('reviewer_id', $reviewerId)
            ->where('status', 'active')
            ->first();
    }
}
