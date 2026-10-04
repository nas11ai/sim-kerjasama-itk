<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Reviewer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-09 — reviewer yang sedang dalam masa tugas; inklusif per hari
 * (end_date = hari terakhir bertugas). Himpunan yang sama dengan
 * Reviewer::isWithinTenure().
 *
 * @method static Builder<Reviewer> run(?CarbonInterface $on = null)
 */
class ReviewersWithinTenure implements ChangesNoState
{
    use AsAction;

    /** @return Builder<Reviewer> */
    public function handle(?CarbonInterface $on = null): Builder
    {
        $day = ($on ?? now())->toDateString();

        return Reviewer::query()
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $day));
    }
}
