<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
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
        // BR-REV-05 — yang dihitung hanya reviewer aktif, dan "selesai" berarti
        // completed atau not_required. Reviewer yang diganti adalah sejarah.
        return $submission->submissionReviewers()
            ->where('status', 'active')
            ->whereNotIn('evaluation_status', SubmissionReviewer::SETTLED_EVALUATION)
            ->exists();
    }
}
