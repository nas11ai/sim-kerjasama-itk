<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\TrendData;
use App\Models\ResearchOutput;
use App\Queries\Submission\FirstSubmitAt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W152 — tab Riwayat: pengajuan disubmit (tanggal submit pertama, W7) dan luaran terdaftar
 * (`research_outputs.created_at`) per bulan pada tahun terpilih, atau per tahun bila "Semua".
 *
 * @method static array{submitted: TrendData, outputs: TrendData} run(StatisticsFilters $filters)
 */
class MonthlyTrend implements ChangesNoState
{
    use AsAction;

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** @return array{submitted: TrendData, outputs: TrendData} */
    public function handle(StatisticsFilters $filters): array
    {
        $submitted = ReportingSubmissions::base($filters)->select(['occurred_at' => FirstSubmitAt::subquery()])->toBase();
        // Luaran milik pengajuan basis periode/skema; tahunnya tahun luaran didaftarkan.
        $outputs = ResearchOutput::query()
            ->whereIn('research_outputs.form_submission_id', ReportingSubmissions::base($filters, withYear: false)->select('form_submissions.id'))
            ->when($filters->year !== null, fn (Builder $q) => $q->whereRaw('EXTRACT(YEAR FROM research_outputs.created_at) = ?', [$filters->year]))
            ->select('research_outputs.created_at as occurred_at')
            ->toBase();

        return [
            'submitted' => $this->trend($submitted, $filters->year),
            'outputs' => $this->trend($outputs, $filters->year),
        ];
    }

    private function trend(QueryBuilder $events, ?int $year): TrendData
    {
        // Nama bagian tanggal dari konstanta kode, bukan input pengguna.
        $part = $year === null ? 'YEAR' : 'MONTH';
        $rows = DB::query()->fromSub($events, 'events')
            ->whereNotNull('occurred_at')
            ->selectRaw("EXTRACT({$part} FROM occurred_at)::int AS bucket, COUNT(*) AS total")
            ->groupByRaw("EXTRACT({$part} FROM occurred_at)::int")
            ->orderBy('bucket')
            ->get();
        $counts = [];

        foreach ($rows as $row) {
            if (is_int($row->bucket)) {
                $counts[$row->bucket] = is_numeric($row->total) ? (int) $row->total : 0;
            }
        }

        return $year === null
            ? new TrendData('year', array_map(fn (int $y): array => ['label' => (string) $y, 'count' => $counts[$y]], array_keys($counts)))
            : new TrendData('month', array_map(fn (string $label, int $i): array => ['label' => $label, 'count' => $counts[$i + 1] ?? 0], self::MONTHS, array_keys(self::MONTHS)));
    }
}
