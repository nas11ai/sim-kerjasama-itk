<?php

namespace App\Data\View\Monev;

use App\Enums\MonevBoardStatus;
use Spatie\LaravelData\Data;

/**
 * Satu baris OPERATOR-05-01. `currentCycle` = nama siklus pertama yang belum `Done`;
 * null bila semua siklus selesai.
 */
final class MonevBoardRowData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $leader,
        public ?string $scheme,
        public ?string $currentCycle,
        public MonevBoardStatus $status,
    ) {}
}
