<?php

namespace App\Data\Reporting;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * W149 — filter Dashboard Statistik. Tahun = tahun submit pertama; tanpa param berarti
 * tahun berjalan, `year=all` berarti semua tahun. Nilai bukan angka diabaikan (null).
 */
final class StatisticsFilters extends Data
{
    public const ALL_YEARS = 'all';

    public function __construct(
        public ?int $year,
        public ?int $period,
        public ?int $scheme,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $year = $request->has('year') ? self::number($request, 'year') : now()->year;

        return new self(
            year: $year,
            period: self::number($request, 'period'),
            scheme: self::number($request, 'scheme'),
        );
    }

    private static function number(Request $request, string $key): ?int
    {
        $value = $request->string($key)->value();

        return ctype_digit($value) ? (int) $value : null;
    }
}
