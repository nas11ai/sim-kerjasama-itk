<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Periode yang tanggal pertamanya jatuh hari ini dan sudah berjalan pada `$now`.
 *
 * @method static Collection<int, SubmissionPeriod> run(CarbonInterface $now)
 */
class PeriodsOpeningToday implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, SubmissionPeriod> */
    public function handle(CarbonInterface $now): Collection
    {
        return SubmissionPeriod::with('submissionDates')
            ->get()
            ->filter(static function (SubmissionPeriod $period) use ($now): bool {
                $dates = $period->submissionDates->sortBy('datetime');
                $first = $dates->first();
                $last = $dates->last();

                if (!$first instanceof SubmissionDate || !$last instanceof SubmissionDate) {
                    return false;
                }

                $start = Carbon::parse($first->datetime);

                // Check if period just became active (started today)
                return $start->isToday() && $now->between($start, Carbon::parse($last->datetime));
            })
            ->values();
    }
}
