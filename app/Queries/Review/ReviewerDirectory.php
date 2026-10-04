<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Reviewer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-09 — daftar reviewer untuk Operator. Filter status memakai definisi masa tugas
 * yang sama dengan penugasan (ReviewersWithinTenure), dan `is_active` tiap baris dari
 * Reviewer::isWithinTenure(), sehingga daftar tidak berbeda pendapat dengan penugasan.
 *
 * @method static LengthAwarePaginator<int, Reviewer> run(?string $search, ?string $type, ?string $status)
 */
class ReviewerDirectory implements ChangesNoState
{
    use AsAction;

    /** @return LengthAwarePaginator<int, Reviewer> */
    public function handle(?string $search, ?string $type, ?string $status): LengthAwarePaginator
    {
        $query = Reviewer::query()->with('user:id,name,email');

        $this->filterSearch($query, $search);
        $this->filterStatus($query, $status);

        if ($type !== null && $type !== '') {
            $query->where('reviewer_type', $type);
        }

        $reviewers = $query->orderByDesc('created_at')->paginate(10);

        $reviewers->getCollection()->each(function (Reviewer $reviewer): void {
            $reviewer->is_active = $reviewer->isWithinTenure();
        });

        return $reviewers;
    }

    /** @param Builder<Reviewer> $query */
    private function filterSearch(Builder $query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $query->where(fn ($q) => $q
            ->whereHas('user', fn ($u) => $u
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%"))
            ->orWhereLike('reviewer_type', "%{$search}%"));
    }

    /** @param Builder<Reviewer> $query */
    private function filterStatus(Builder $query, ?string $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $withinTenure = ReviewersWithinTenure::run()->select('id');

        $status === 'active'
            ? $query->whereIn('id', $withinTenure)
            : $query->whereNotIn('id', $withinTenure);
    }
}
