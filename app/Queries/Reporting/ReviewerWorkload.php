<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Reporting\RankedCountData;
use App\Data\View\Reporting\ReviewerWorkloadData;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Review\ReviewersWithinTenure;
use App\Support\Identity\ReviewerTypeCatalog;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W152 — "Beban Reviewer": evaluasi aktif yang belum selesai (BR-REV-05) per reviewer aktif
 * (definisi W150), ditambah jumlah reviewer aktif tanpa tugas. Keadaan sekarang, tanpa filter.
 *
 * @method static ReviewerWorkloadData run()
 */
class ReviewerWorkload implements ChangesNoState
{
    use AsAction;

    public function handle(): ReviewerWorkloadData
    {
        $reviewers = ReviewersWithinTenure::run()
            ->whereHas('user', fn (Builder $user) => $user->where('is_active', true))
            ->with('user:id,name')
            ->withCount(['submissionReviewers as open_evaluations' => $this->unsettled(...)])
            ->get();
        $busy = $reviewers->filter(fn (Reviewer $reviewer): bool => $this->open($reviewer) > 0);

        return new ReviewerWorkloadData(
            rows: RankedCountData::top(array_values($busy->map(fn (Reviewer $reviewer): RankedCountData => new RankedCountData(
                label: $this->label($reviewer),
                count: $this->open($reviewer),
            ))->all())),
            idleCount: $reviewers->count() - $busy->count(),
        );
    }

    /** @param  Builder<SubmissionReviewer>  $seats */
    private function unsettled(Builder $seats): void
    {
        $seats->unsettledEvaluation();
    }

    private function label(Reviewer $reviewer): string
    {
        $type = ReviewerTypeCatalog::LABELS[$reviewer->reviewer_type] ?? $reviewer->reviewer_type;

        return ($reviewer->user->name ?? 'Penilai')." ({$type})";
    }

    private function open(Reviewer $reviewer): int
    {
        $count = $reviewer->getAttribute('open_evaluations');

        return is_numeric($count) ? (int) $count : 0;
    }
}
