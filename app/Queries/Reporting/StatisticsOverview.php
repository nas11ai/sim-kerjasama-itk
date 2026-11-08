<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Dashboard\KpiCardData;
use App\Data\View\Reporting\RankedCountData;
use App\Data\View\Reporting\SchemeYearData;
use App\Data\View\Reporting\StatusShareData;
use App\Models\BudgetLineItem;
use App\Queries\Review\ReviewersWithinTenure;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\Support\Format\Rupiah;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-07-01 tab Ringkasan — KPI (W150) dan empat panel frame (W151), semuanya dari
 * basis W149. Nilai KPI sudah berbentuk teks tampilan; frontend tidak menghitung ulang.
 *
 * @phpstan-type Overview array{kpis: list<KpiCardData>, byStatus: list<StatusShareData>, bySchemeYear: SchemeYearData, byFaculty: list<RankedCountData>, byStudyProgram: list<RankedCountData>, byOutputType: list<RankedCountData>}
 *
 * @method static Overview run(StatisticsFilters $filters, bool $withLinks)
 */
class StatisticsOverview implements ChangesNoState
{
    use AsAction;

    /** @return Overview */
    public function handle(StatisticsFilters $filters, bool $withLinks): array
    {
        $byStatus = StatusBreakdown::run($filters, $withLinks);
        $byOutputType = OutputTypeCounts::run($filters);

        return [
            'kpis' => [
                $this->submissions($filters, $byStatus, $withLinks),
                new KpiCardData('approvedBudget', 'Total Anggaran Disetujui', Rupiah::format($this->approvedBudget($filters)), 'Pengajuan disetujui', null),
                new KpiCardData('outputs', 'Luaran Terkumpul', (string) array_sum(array_map(fn (RankedCountData $r): int => $r->count, $byOutputType)), 'Semua tipe', null),
                $this->activeReviewers(),
                $this->approvalRate($byStatus),
            ],
            'byStatus' => $byStatus,
            'bySchemeYear' => SchemeYearCounts::run($filters),
            ...OrganizationCounts::run($filters),
            'byOutputType' => $byOutputType,
        ];
    }

    /** @param  list<StatusShareData>  $byStatus */
    private function submissions(StatisticsFilters $filters, array $byStatus, bool $withLinks): KpiCardData
    {
        return new KpiCardData(
            key: 'submissions',
            label: 'Total Submission',
            value: (string) array_sum(array_map(fn (StatusShareData $s): int => $s->count, $byStatus)),
            meta: $filters->year === null ? 'Semua tahun' : "Tahun {$filters->year}",
            href: $withLinks ? route('admin.submissions.index', $filters->query()) : null,
        );
    }

    // W150 — item yang dihapus (soft delete) tidak ikut; hanya pengajuan ber-state Approved.
    private function approvedBudget(StatisticsFilters $filters): int
    {
        $approved = ReportingSubmissions::base($filters)
            ->where('form_submissions.status', Approved::getMorphClass())
            ->select('form_submissions.id');

        // PostgreSQL mengembalikan SUM bigint sebagai numeric-string.
        return (int) BudgetLineItem::query()->whereIn('form_submission_id', $approved)->sum('total');
    }

    // W150 — definisi yang sama dengan ReviewerCandidates; keadaan sekarang, tanpa filter tahun.
    private function activeReviewers(): KpiCardData
    {
        $rows = ReviewersWithinTenure::run()
            ->whereHas('user', fn (Builder $user) => $user->where('is_active', true))
            ->toBase()
            ->select('reviewer_type')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('reviewer_type')
            ->pluck('total', 'reviewer_type');
        $internal = is_numeric($rows->get('internal')) ? (int) $rows->get('internal') : 0;
        $external = is_numeric($rows->get('external')) ? (int) $rows->get('external') : 0;

        return new KpiCardData('activeReviewers', 'Reviewer Aktif', (string) ($internal + $external), "Internal {$internal} · Eksternal {$external}", null);
    }

    /**
     * W150 — Disetujui ÷ (Disetujui + Ditolak); WITHDRAWN bukan keputusan review.
     *
     * @param  list<StatusShareData>  $byStatus
     */
    private function approvalRate(array $byStatus): KpiCardData
    {
        $count = fn (string $key): int => array_sum(array_map(
            fn (StatusShareData $s): int => $s->key === $key ? $s->count : 0,
            $byStatus,
        ));
        $approved = $count(Approved::getMorphClass());
        $decided = $approved + $count(Rejected::getMorphClass());
        $value = $decided === 0 ? '—' : round($approved / $decided * 100).'%';

        return new KpiCardData('approvalRate', 'Tingkat Persetujuan', $value, 'Dari yang sudah diputuskan', null);
    }
}
