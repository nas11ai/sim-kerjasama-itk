<?php

namespace App\Data\View\Dashboard;

use Spatie\LaravelData\Data;

/** Satu section kartu dasbor; `key` = researcher|reviewer|management (W45). */
final class DashboardSectionData extends Data
{
    /** @param  list<KpiCardData>  $cards */
    public function __construct(
        public string $key,
        public string $title,
        public array $cards,
    ) {}
}
