<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\RankedCountData;
use App\Enums\MonevBoardStatus;
use App\Models\FormSubmission;
use App\Queries\Monev\MonevCycleBoard;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W153 — "Siklus Monev": induk papan OPERATOR-05-01 per status papan, urutan enum (bukan
 * peringkat). Siklus dihitung per induk (N+1 yang sudah tercatat di M1-2; aman di skala SIMPAS).
 *
 * @method static list<RankedCountData> run(StatisticsFilters $filters, bool $withLinks)
 */
class MonevStatusCounts implements ChangesNoState
{
    use AsAction;

    /** @return list<RankedCountData> */
    public function handle(StatisticsFilters $filters, bool $withLinks): array
    {
        $counts = MonevCycleBoard::parents(ReportingSubmissions::base($filters, withYear: false))
            ->with('formPhaseDetail')
            ->get()
            ->countBy(fn (FormSubmission $parent): string => MonevBoardStatus::forParent($parent)->value);
        // Papan monev tidak punya filter status (W131), jadi tautannya membawa periode/skema saja.
        $href = $withLinks ? route('monev.manage.index', $filters->withoutYear()->query()) : null;

        return array_map(fn (MonevBoardStatus $status): RankedCountData => new RankedCountData(
            label: $status->label(),
            count: $counts->get($status->value, 0),
            href: $href,
        ), MonevBoardStatus::cases());
    }
}
