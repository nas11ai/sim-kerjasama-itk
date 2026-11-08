<?php

namespace App\Support\Reporting\Exports;

use App\Data\Reporting\ExportFilters;
use App\Models\BudgetLineItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * W154 — Detail Anggaran: satu baris per item anggaran pengajuan yang diekspor. Item yang
 * dihapus (soft delete) tidak ikut, sama dengan total di Rekap Submission.
 */
final class BudgetDetailExport implements ExportDefinition
{
    public function label(): string
    {
        return 'Detail Anggaran';
    }

    public function filterKeys(): array
    {
        return ExportedSubmissions::FILTER_KEYS;
    }

    public function headings(): array
    {
        return ['ID Pengajuan', 'Judul', 'Komponen', 'Item', 'Volume', 'Satuan', 'Harga Satuan', 'Total'];
    }

    public function rowCount(User $user, ExportFilters $filters): int
    {
        return $this->query($user, $filters)->count();
    }

    public function rows(User $user, ExportFilters $filters): array
    {
        $items = $this->query($user, $filters)
            ->with(['formSubmission:id,form_id', 'formSubmission.form:id,title', 'budgetComponent:id,name'])
            ->orderBy('form_submission_id')
            ->orderBy('id')
            ->get();

        return array_values($items->map(fn (BudgetLineItem $item): array => [
            $item->form_submission_id,
            $item->formSubmission->form->title,
            $item->budgetComponent->name,
            $item->item_name,
            $item->volume,
            $item->unit,
            $item->unit_price,
            $item->total,
        ])->all());
    }

    /** @return Builder<BudgetLineItem> */
    private function query(User $user, ExportFilters $filters): Builder
    {
        return BudgetLineItem::query()->whereIn('form_submission_id', ExportedSubmissions::idsFor($user, $filters));
    }
}
