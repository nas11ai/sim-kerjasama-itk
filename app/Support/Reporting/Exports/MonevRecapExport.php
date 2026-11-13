<?php

namespace App\Support\Reporting\Exports;

use App\Data\Reporting\ExportFilters;
use App\Data\View\Monev\MonevCycleData;
use App\Enums\DeadlineType;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Monev\MonevCycles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * W154 — Rekap Monev: satu baris per induk per siklus beserta keadaannya. Siklus diturunkan
 * `MonevCycles` (W125), bukan baris tabel, jadi barisnya dibentangkan di sini.
 */
final class MonevRecapExport implements ExportDefinition
{
    public function label(): string
    {
        return 'Rekap Monev';
    }

    public function filterKeys(): array
    {
        return ExportedSubmissions::FILTER_KEYS;
    }

    public function headings(): array
    {
        return ['ID Pengajuan', 'Judul', 'Siklus', 'Status Siklus'];
    }

    /** BR-RPT-02 — padanan SQL `MonevCycles::details()` per induk: jumlah siklus = baris berkas. */
    public function rowCount(User $user, ExportFilters $filters): int
    {
        return $this->parents($user, $filters)
            ->join('form_phase_details as own', 'own.id', '=', 'form_submissions.form_phase_detail_id')
            ->join('form_phase_details as later', fn (JoinClause $join) => $join
                ->on('later.form_phase_version_id', '=', 'own.form_phase_version_id')
                ->on('later.order', '>', 'own.order'))
            ->where('later.deadline_type', '!=', DeadlineType::Output->value)
            ->count();
    }

    public function rows(User $user, ExportFilters $filters): array
    {
        $rows = [];

        // MonevCycles membaca langkah dan kalender periode tiap induk; dimuat sekali untuk semua induk.
        $parents = $this->parents($user, $filters)
            ->with(['form:id,title', 'formPhaseDetail', 'submissionPeriod.submissionDates'])
            ->orderBy('form_submissions.id')
            ->get();

        foreach ($parents as $parent) {
            foreach (MonevCycles::run($parent) as $cycle) {
                $rows[] = self::row($parent, $cycle);
            }
        }

        return $rows;
    }

    /** @return list<string|int|null> */
    private static function row(FormSubmission $parent, MonevCycleData $cycle): array
    {
        return [$parent->id, $parent->displayTitle(), $cycle->name, $cycle->state->label()];
    }

    /** @return Builder<FormSubmission> */
    private function parents(User $user, ExportFilters $filters): Builder
    {
        return MonevCycles::whereHasCycles(ExportedSubmissions::for($user, $filters));
    }
}
