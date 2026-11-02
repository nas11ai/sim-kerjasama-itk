<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Identity\ReviewerRowData;
use App\Enums\ReviewerStanding;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Support\Search\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-09 — daftar reviewer untuk Operator (OPERATOR-08-04). Kedudukan memakai definisi
 * masa tugas yang sama dengan penugasan (ReviewerStanding), sehingga daftar tidak berbeda
 * pendapat dengan penugasan. Nilai filter tak dikenal diabaikan.
 *
 * @phpstan-type Filters array{search: string, type: string, standing: string}
 *
 * @method static LengthAwarePaginator<int, ReviewerRowData> run(Filters $filters, bool $linksHistory)
 */
class ReviewerDirectory implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 20;

    /**
     * @param  Filters  $filters
     * @return LengthAwarePaginator<int, ReviewerRowData>
     */
    public function handle(array $filters, bool $linksHistory): LengthAwarePaginator
    {
        $query = Reviewer::query()
            ->with('user:id,name,email,is_active')
            // W54 — beban tugas = penugasan aktif yang evaluasinya belum beres.
            ->withCount(['submissionReviewers as running_assignments' => $this->unsettled(...)])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->filter($query, $filters);

        return $query->paginate(self::PER_PAGE)->withQueryString()
            ->through(fn (Reviewer $reviewer): ReviewerRowData => ReviewerRowData::fromModel($reviewer, $linksHistory));
    }

    /** @param  Builder<SubmissionReviewer>  $assignments */
    private function unsettled(Builder $assignments): void
    {
        $assignments->unsettledEvaluation();
    }

    /**
     * @param  Builder<Reviewer>  $query
     * @param  Filters  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $pattern = LikePattern::contains($filters['search']);
            $query->whereHas('user', fn (Builder $user) => $user
                ->where(fn (Builder $q) => $q->whereLike('name', $pattern)->orWhereLike('email', $pattern)));
        }

        if (in_array($filters['type'], ['internal', 'external'], true)) {
            $query->where('reviewer_type', $filters['type']);
        }

        $standing = ReviewerStanding::tryFrom($filters['standing']);

        if ($standing !== null) {
            ReviewerStanding::scope($query, $standing);
        }
    }
}
