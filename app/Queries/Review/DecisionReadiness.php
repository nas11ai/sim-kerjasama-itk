<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Review\DecisionReadinessData;
use App\Data\View\Review\ReviewRoundProgressData;
use App\Models\FormSubmission;
use App\Models\ReviewRound;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-05, BR-REV-07 — "siap diputuskan": keadaan turunan dari data putaran, bukan kolom (spec
 * §4.2). `handle()` untuk satu pengajuan, `whereReady()` padanan SQL-nya untuk daftar dan dashboard.
 *
 * @method static DecisionReadinessData run(FormSubmission $submission)
 */
class DecisionReadiness implements ChangesNoState
{
    use AsAction;

    /** Status yang menunggu keputusan; Action keputusan melewatkannya ke UNDER_REVIEW bila perlu. */
    private const AWAITING_DECISION = [Submitted::class, UnderReview::class, Resubmitted::class];

    public function handle(FormSubmission $submission): DecisionReadinessData
    {
        $openThreadCount = $submission->reviewSummaries()->where('status', 'open')->count();
        $running = $submission->runningReviewRound();
        $latest = $running ?? $submission->latestReviewRound();
        $ready = $this->isReady($submission, $latest);

        return new DecisionReadinessData(
            ready: $ready,
            approvable: $ready && $openThreadCount === 0,
            blockers: $this->blockers($submission, $running, $openThreadCount),
            openThreadCount: $openThreadCount,
            round: $latest === null ? null : ReviewRoundProgressData::of($latest),
        );
    }

    /**
     * Padanan SQL `ready`. Dijaga sama oleh DecisionReadinessTest atas dataset campuran.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public static function whereReady(Builder $query): Builder
    {
        $awaiting = array_map(fn (string $state): string => $state::$name, self::AWAITING_DECISION);

        return $query
            ->whereNull('parent_submission_id')
            ->whereIn('status', $awaiting)
            ->where(fn (Builder $outcome) => $outcome
                ->whereHas('formPhaseDetail', fn (Builder $detail) => $detail->where('needs_review', false))
                ->orWhere(fn (Builder $reviewed) => $reviewed
                    ->whereHas('submissionReviewers', fn (Builder $seat) => $seat->where('status', 'active'))
                    ->whereDoesntHave('reviewRounds', fn (Builder $round) => $round->whereNull('completed_at'))
                    ->whereHas('reviewRounds', fn (Builder $round) => $round
                        ->where('outcome', ReviewRound::OUTCOME_READY)
                        ->whereRaw('number = (select max(r.number) from review_rounds r where r.form_submission_id = review_rounds.form_submission_id)'))));
    }

    private function isReady(FormSubmission $submission, ?ReviewRound $latest): bool
    {
        if ($submission->parent_submission_id !== null || !$this->awaitsDecision($submission)) {
            return false;
        }

        // Spec §4.6 — fase tanpa review langsung siap; tanpa detail fase dianggap butuh review.
        if (!$this->needsReview($submission)) {
            return true;
        }

        return $submission->activeSubmissionReviewers()->exists()
            && $latest !== null
            && !$latest->isRunning()
            && $latest->outcome === ReviewRound::OUTCOME_READY;
    }

    /** @return list<string> */
    private function blockers(FormSubmission $submission, ?ReviewRound $running, int $openThreadCount): array
    {
        $blockers = [];

        if ($this->needsReview($submission) && !$submission->activeSubmissionReviewers()->exists()) {
            $blockers[] = 'Belum ada penilai.';
        }

        if ($running !== null) {
            $progress = ReviewRoundProgressData::of($running);
            $waiting = $progress->total - $progress->finished;
            $blockers[] = "{$waiting} dari {$progress->total} penilai belum menyelesaikan putaran.";
        }

        if ($openThreadCount > 0) {
            $blockers[] = "{$openThreadCount} catatan penilai masih terbuka.";
        }

        return $blockers;
    }

    private function awaitsDecision(FormSubmission $submission): bool
    {
        $status = $submission->status;

        return $status instanceof SubmissionStatus && $status->equals(...self::AWAITING_DECISION);
    }

    private function needsReview(FormSubmission $submission): bool
    {
        return $submission->formPhaseDetail === null || $submission->formPhaseDetail->needs_review;
    }
}
