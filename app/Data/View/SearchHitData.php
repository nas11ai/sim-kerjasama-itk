<?php

namespace App\Data\View;

use Spatie\LaravelData\Data;

/** K2 — satu hasil pencarian global: label utama, sublabel konteks, dan tujuan navigasinya. */
final class SearchHitData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public string $sublabel,
        public string $url,
    ) {}
}
