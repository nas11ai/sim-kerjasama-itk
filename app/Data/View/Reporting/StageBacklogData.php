<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/**
 * Satu tahap "Pengajuan Berjalan" (W152). `oldestAgeDays` = hari penuh sejak submit pertama
 * yang terlama, null bila tahap kosong; `href` null tanpa submissions.view-all (W148).
 */
final class StageBacklogData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
        public ?int $oldestAgeDays,
        public ?string $href,
    ) {}
}
