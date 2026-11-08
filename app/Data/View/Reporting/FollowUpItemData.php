<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/** Satu pengajuan di daftar Tindak Lanjut (W152); `ageDays` = hari penuh sejak submit pertama. */
final class FollowUpItemData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $leader,
        public ?string $scheme,
        public int $ageDays,
        public string $href,
    ) {}
}
