<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/** Satu baris rencana anggaran (BR-BUD-01). */
final class BudgetItemData extends Data
{
    public function __construct(
        public string $component,
        public string $item,
        public int $volume,
        public string $unit,
        public int $unitPrice,
        public int $total,
    ) {}
}
