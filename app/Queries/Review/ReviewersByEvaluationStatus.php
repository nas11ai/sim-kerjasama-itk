<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reviewer sebuah pengajuan yang evaluasinya berstatus tertentu.
 *
 * @method static Collection<int, SubmissionReviewer> run(FormSubmission $submission, string $status)
 */
class ReviewersByEvaluationStatus implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, SubmissionReviewer> */
    public function handle(FormSubmission $submission, string $status): Collection
    {
        return $submission->submissionReviewers()
            ->where('evaluation_status', $status)
            ->with(['reviewer.user'])
            ->get();
    }
}
