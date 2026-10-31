<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Review\EvaluationSubmissionData;
use App\Data\View\Review\OutcomeConditionsData;
use App\Data\View\Submission\Revision\ReviewThreadData;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * REVIEWER-03-04 — ringkasan dan diskusi satu penugasan reviewer. Penugasan aktif milik
 * pemanggil (BR-SM-07, BR-REV-10) dijaga controller sebelum Query ini dipanggil.
 *
 * @phpstan-import-type Score from VisibleReviewerScores
 *
 * @phpstan-type Summary array{submission: EvaluationSubmissionData, threads: list<ReviewThreadData>, canCreateThread: bool, scores: array{visible: bool, reason: string|null, scores: list<Score>}, outcome: OutcomeConditionsData}
 *
 * @method static Summary run(User $viewer, SubmissionReviewer $assignment)
 */
class ReviewerSummary implements ChangesNoState
{
    use AsAction;

    /** @return Summary */
    public function handle(User $viewer, SubmissionReviewer $assignment): array
    {
        $submission = $assignment->formSubmission;
        $threads = ReviewThreads::run($submission);

        return [
            'submission' => EvaluationSubmissionData::of($submission),
            // BR-REV-15 — ReplaceReviewer memindahkan `reviewer_id` ulir terbuka ke pengganti,
            // jadi saringan ini ikut membawa ulir warisan.
            'threads' => ReviewThreadData::many(
                $threads['summaries']->where('reviewer_id', $assignment->reviewer_id)->values(),
                $threads['comments'],
            ),
            // BR-FE-06 — definisi yang sama dengan ReviewSummaryGuard.
            'canCreateThread' => $assignment->hasSettledEvaluation(),
            'scores' => VisibleReviewerScores::run($viewer, $submission),
            'outcome' => new OutcomeConditionsData(
                reviewerCount: $threads['stats']['total_reviewers'],
                unsettledCount: $submission->submissionReviewers()->unsettledEvaluation()->count(),
                openThreadCount: $threads['stats']['open_reviews'],
            ),
        ];
    }
}
