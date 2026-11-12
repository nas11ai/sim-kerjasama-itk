<?php

namespace App\Data\View\Period;

use Spatie\LaravelData\Data;

/** BR-FE-36, G4-W8 — satu isu cek kesiapan: memblokir (dibutuhkan sekarang) atau peringatan (nanti). */
final class ReadinessIssueData extends Data
{
    public function __construct(
        public string $message,
        public bool $blocking,
    ) {}
}
