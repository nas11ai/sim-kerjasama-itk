<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/**
 * Satu kartu Tindak Lanjut (W152): jumlah, maks lima pengajuan terlama, dan tautan ke daftar
 * tempat aksinya. `items` kosong dan `href` null tanpa submissions.view-all (W148).
 */
final class FollowUpListData extends Data
{
    /** @param  list<FollowUpItemData>  $items */
    public function __construct(
        public string $key,
        public string $title,
        public string $hint,
        public int $count,
        public array $items,
        public ?string $href,
    ) {}
}
