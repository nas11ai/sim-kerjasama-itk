<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/**
 * "Submission per Skema per Tahun" (W151): kolom berkelompok, tahun menaik. `counts` dikunci
 * tahun dan selalu memuat ketiga tahun, jadi JSON-nya objek `{"2024": 1, ...}`.
 */
final class SchemeYearData extends Data
{
    /**
     * @param  list<int>  $years
     * @param  list<array{scheme: string, counts: array<int, int>}>  $rows
     */
    public function __construct(
        public array $years,
        public array $rows,
    ) {}
}
