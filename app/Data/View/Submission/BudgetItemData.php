<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/** Satu baris rencana anggaran (BR-BUD-01); `id`/`componentId` dipakai form ubah langkah 5. */
final class BudgetItemData extends Data
{
    public function __construct(
        public int $id,
        public int $componentId,
        public string $component,
        public string $item,
        public int $volume,
        public string $unit,
        public int $unitPrice,
        public int $total,
    ) {}
}
