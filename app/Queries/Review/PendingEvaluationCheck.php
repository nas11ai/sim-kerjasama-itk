<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Apakah pengajuan ini masih menunggu evaluasi reviewer.
 *
 * @method static bool run(FormSubmission $submission)
 */
class PendingEvaluationCheck implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): bool
    {
        // BR-REV-05 — definisinya tinggal di SubmissionReviewer, bukan disalin
        // ke sini: canProceed() memakai scope yang sama persis.
        return $submission->submissionReviewers()->unsettledEvaluation()->exists();
    }
}
