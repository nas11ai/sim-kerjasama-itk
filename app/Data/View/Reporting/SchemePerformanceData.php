<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/**
 * Satu baris "Kinerja per Skema" (W152). Teks tingkat persetujuan dan anggaran memakai definisi
 * KPI W150; `href` ke daftar Operator berfilter skema, null tanpa submissions.view-all (W148).
 */
final class SchemePerformanceData extends Data
{
    public function __construct(
        public string $scheme,
        public int $submitted,
        public int $approved,
        public int $rejected,
        public int $withdrawn,
        public string $approvalRate,
        public string $approvedBudget,
        public ?string $href,
    ) {}
}
