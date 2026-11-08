<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/**
 * Satu grafik tren (W152, satu seri — small multiples W151). `unit` 'month' = 12 titik
 * "Jan".."Des" pada tahun terpilih; 'year' = satu titik per tahun yang punya data.
 */
final class TrendData extends Data
{
    /** @param  list<array{label: string, count: int}>  $points */
    public function __construct(
        public string $unit,
        public array $points,
    ) {}
}
