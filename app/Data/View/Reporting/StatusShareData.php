<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/**
 * Satu baris "Submission per Status" (W151). `share` 0–1, 0 bila total 0; `href` ke daftar
 * Operator berfilter, null bila pembaca tidak memegang submissions.view-all.
 */
final class StatusShareData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $variant,
        public int $count,
        public float $share,
        public ?string $href,
    ) {}
}
