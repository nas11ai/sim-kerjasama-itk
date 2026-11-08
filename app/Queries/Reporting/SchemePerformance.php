<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\SchemePerformanceData;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use App\Support\Format\Rupiah;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W152 — "Kinerja per Skema" di tab Riwayat, dari basis W149 dan definisi KPI W150, sehingga
 * jumlah semua baris sama dengan kartu Ringkasan pada filter yang sama. Terbanyak diajukan dulu.
 *
 * @phpstan-type Counts array{scheme: string, submitted: int, approved: int, rejected: int, withdrawn: int}
 *
 * @method static list<SchemePerformanceData> run(StatisticsFilters $filters, bool $withLinks)
 */
class SchemePerformance implements ChangesNoState
{
    use AsAction;

    /** Pengajuan tanpa skema tetap terhitung di Total Submission, jadi tetap punya baris. */
    private const NO_SCHEME = 'Tanpa skema';

    /** @return list<SchemePerformanceData> */
    public function handle(StatisticsFilters $filters, bool $withLinks): array
    {
        $budgets = $this->budgets($filters);
        $rows = [];

        foreach ($this->counts($filters) as $id => $c) {
            $rows[] = new SchemePerformanceData(
                scheme: $c['scheme'],
                submitted: $c['submitted'],
                approved: $c['approved'],
                rejected: $c['rejected'],
                withdrawn: $c['withdrawn'],
                approvalRate: KpiDefinitions::approvalRate($c['approved'], $c['rejected']),
                approvedBudget: Rupiah::format($budgets[$id] ?? 0),
                href: $withLinks && $id !== 0 ? route('admin.submissions.index', ['scheme' => $id, ...$filters->query()]) : null,
            );
        }

        usort($rows, fn (SchemePerformanceData $a, SchemePerformanceData $b): int => [$b->submitted, $a->scheme] <=> [$a->submitted, $b->scheme]);

        return $rows;
    }

    /** @return array<int, Counts> per id skema; 0 = tanpa skema */
    private function counts(StatisticsFilters $filters): array
    {
        $found = ReportingSubmissions::base($filters)->toBase()
            ->leftJoin('schemes', 'schemes.id', '=', 'form_submissions.scheme_id')
            ->select('form_submissions.scheme_id', 'schemes.name')
            ->selectRaw('COUNT(*) AS submitted')
            ->selectRaw('COUNT(*) FILTER (WHERE form_submissions.status = ?) AS approved', [Approved::getMorphClass()])
            ->selectRaw('COUNT(*) FILTER (WHERE form_submissions.status = ?) AS rejected', [Rejected::getMorphClass()])
            ->selectRaw('COUNT(*) FILTER (WHERE form_submissions.status = ?) AS withdrawn', [Withdrawn::getMorphClass()])
            ->groupBy('form_submissions.scheme_id', 'schemes.name')
            ->get();
        $counts = [];

        foreach ($found as $row) {
            $counts[is_int($row->scheme_id) ? $row->scheme_id : 0] = [
                'scheme' => is_string($row->name) ? $row->name : self::NO_SCHEME,
                'submitted' => is_numeric($row->submitted) ? (int) $row->submitted : 0,
                'approved' => is_numeric($row->approved) ? (int) $row->approved : 0,
                'rejected' => is_numeric($row->rejected) ? (int) $row->rejected : 0,
                'withdrawn' => is_numeric($row->withdrawn) ? (int) $row->withdrawn : 0,
            ];
        }

        return $counts;
    }

    /** @return array<int, int> anggaran disetujui (W150) per id skema; 0 = tanpa skema */
    private function budgets(StatisticsFilters $filters): array
    {
        $found = KpiDefinitions::approvedBudgetLines($filters)->toBase()
            ->join('form_submissions', 'form_submissions.id', '=', 'budget_line_items.form_submission_id')
            ->select('form_submissions.scheme_id')
            ->selectRaw('SUM(budget_line_items.total) AS total')
            ->groupBy('form_submissions.scheme_id')
            ->get();
        $budgets = [];

        foreach ($found as $row) {
            $budgets[is_int($row->scheme_id) ? $row->scheme_id : 0] = is_numeric($row->total) ? (int) $row->total : 0;
        }

        return $budgets;
    }
}
