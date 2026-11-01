<?php

namespace App\Data\View\Dashboard;

use Spatie\LaravelData\Data;

/** Satu kartu ringkasan dasbor (SHARED-02-01..04); `value` sudah berbentuk teks tampilan. */
final class KpiCardData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $value,
        public ?string $meta,
        public ?string $href,
    ) {}
}
