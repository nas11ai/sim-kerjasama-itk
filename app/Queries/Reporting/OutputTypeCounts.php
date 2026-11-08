<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\RankedCountData;
use App\Models\ResearchOutput;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W150/W151 — "Luaran per Tipe": luaran terdaftar milik pengajuan di basis W149, berlabel nama
 * tipe dari data acuan (cadangan: kodenya, bila tipe sudah tidak terdefinisi).
 *
 * @method static list<RankedCountData> run(StatisticsFilters $filters)
 */
class OutputTypeCounts implements ChangesNoState
{
    use AsAction;

    /** @return list<RankedCountData> */
    public function handle(StatisticsFilters $filters): array
    {
        $rows = ResearchOutput::query()->toBase()
            ->leftJoin('output_type_definitions as type', 'type.code', '=', 'research_outputs.output_type')
            ->whereIn('research_outputs.form_submission_id', ReportingSubmissions::base($filters)->select('form_submissions.id'))
            ->select('research_outputs.output_type', 'type.name')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('research_outputs.output_type', 'type.name')
            ->get();
        $ranked = [];

        foreach ($rows as $row) {
            $ranked[] = new RankedCountData(
                label: is_string($row->name) ? $row->name : (is_string($row->output_type) ? $row->output_type : ''),
                count: is_numeric($row->total) ? (int) $row->total : 0,
            );
        }

        return RankedCountData::top($ranked);
    }
}
