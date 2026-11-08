<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/** "Beban Reviewer" (W152): reviewer aktif bertugas, terbanyak dulu, dan yang belum bertugas. */
final class ReviewerWorkloadData extends Data
{
    /** @param  list<RankedCountData>  $rows */
    public function __construct(
        public array $rows,
        public int $idleCount,
    ) {}
}
