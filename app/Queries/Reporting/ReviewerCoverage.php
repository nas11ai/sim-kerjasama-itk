<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\RankedCountData;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * DDD "distribusi reviewer per submission" (W150): pengajuan berjalan menurut jumlah penugasan
 * aktif (BR-REV-15 — yang `replaced` bukan tim review hari ini). Empat baris tetap.
 *
 * @method static list<RankedCountData> run(StatisticsFilters $filters)
 */
class ReviewerCoverage implements ChangesNoState
{
    use AsAction;

    private const BUCKETS = ['Tanpa reviewer', '1 reviewer', '2 reviewer', '3+ reviewer'];

    /** @return list<RankedCountData> */
    public function handle(StatisticsFilters $filters): array
    {
        $buckets = $this->buckets($filters);

        return array_map(
            fn (int $seats): RankedCountData => new RankedCountData(self::BUCKETS[$seats], $buckets[$seats] ?? 0),
            array_keys(self::BUCKETS),
        );
    }

    /** @return array<int, int> jumlah pengajuan per 0, 1, 2, 3+ penugasan aktif */
    private function buckets(StatisticsFilters $filters): array
    {
        $running = ReportingSubmissions::base($filters, withYear: false)
            ->whereIn('form_submissions.status', StageBacklog::runningKeys())
            ->select('form_submissions.id')
            ->withCount('activeSubmissionReviewers');
        $rows = DB::query()->fromSub($running, 'running')
            ->selectRaw('LEAST(active_submission_reviewers_count, 3) AS seats, COUNT(*) AS total')
            ->groupByRaw('LEAST(active_submission_reviewers_count, 3)')
            ->get();
        $buckets = [];

        foreach ($rows as $row) {
            if (is_numeric($row->seats)) {
                $buckets[(int) $row->seats] = is_numeric($row->total) ? (int) $row->total : 0;
            }
        }

        return $buckets;
    }
}
