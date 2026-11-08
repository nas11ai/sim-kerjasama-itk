<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\SchemeYearData;
use App\Queries\Submission\SubmissionYear;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W151 — "Submission per Skema per Tahun": tiga tahun submit terakhir yang punya data.
 * Filter Tahun diabaikan karena perbandingan antartahun adalah isinya; periode/skema berlaku.
 *
 * @method static SchemeYearData run(StatisticsFilters $filters)
 */
class SchemeYearCounts implements ChangesNoState
{
    use AsAction;

    private const YEARS = 3;

    public function handle(StatisticsFilters $filters): SchemeYearData
    {
        $counts = $this->counts($filters);
        $years = $this->latestYears($counts);
        $rows = [];

        foreach ($counts as $scheme => $byYear) {
            $shown = array_intersect_key($byYear, array_flip($years));
            if ($shown !== []) {
                $rows[] = ['scheme' => (string) $scheme, 'counts' => array_replace(array_fill_keys($years, 0), $shown)];
            }
        }

        return new SchemeYearData($years, $rows);
    }

    /**
     * @param  array<string, array<int, int>>  $counts
     * @return list<int> menaik
     */
    private function latestYears(array $counts): array
    {
        $years = [];

        foreach ($counts as $byYear) {
            foreach (array_keys($byYear) as $year) {
                $years[$year] = $year;
            }
        }
        ksort($years);

        return array_slice(array_values($years), -self::YEARS);
    }

    /** @return array<string, array<int, int>> jumlah per nama skema lalu per tahun, skema menaik */
    private function counts(StatisticsFilters $filters): array
    {
        $submissions = ReportingSubmissions::base($filters, withYear: false)
            ->join('schemes', 'schemes.id', '=', 'form_submissions.scheme_id')
            ->select(['schemes.name as scheme', 'submit_year' => SubmissionYear::subquery()]);
        $rows = DB::query()->fromSub($submissions, 'submissions')
            ->whereNotNull('submit_year')
            ->select('scheme', 'submit_year')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('scheme', 'submit_year')
            ->orderBy('scheme')
            ->get();
        $counts = [];

        foreach ($rows as $row) {
            if (is_string($row->scheme) && is_int($row->submit_year)) {
                $counts[$row->scheme][$row->submit_year] = is_numeric($row->total) ? (int) $row->total : 0;
            }
        }

        return $counts;
    }
}
