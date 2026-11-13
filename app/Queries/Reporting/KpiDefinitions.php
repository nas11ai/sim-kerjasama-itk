<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Models\BudgetLineItem;
use App\States\Submission\Approved;
use Illuminate\Database\Eloquent\Builder;

/**
 * W150 — definisi KPI yang dipakai lebih dari satu panel (Ringkasan dan kinerja skema),
 * supaya angka yang sama tidak pernah dihitung dengan dua cara.
 */
final class KpiDefinitions implements ChangesNoState
{
    /**
     * Baris anggaran pengajuan basis ber-state Approved; yang dihapus (soft delete) tidak ikut.
     *
     * @return Builder<BudgetLineItem>
     */
    public static function approvedBudgetLines(StatisticsFilters $filters): Builder
    {
        $approved = ReportingSubmissions::base($filters)
            ->where('form_submissions.status', Approved::getMorphClass())
            ->select('form_submissions.id');

        return BudgetLineItem::query()->whereIn('budget_line_items.form_submission_id', $approved);
    }

    /** Disetujui ÷ (Disetujui + Ditolak); WITHDRAWN bukan keputusan review. Penyebut 0 → "—". */
    public static function approvalRate(int $approved, int $rejected): string
    {
        $decided = $approved + $rejected;

        return $decided === 0 ? '-' : round($approved / $decided * 100).'%';
    }
}
