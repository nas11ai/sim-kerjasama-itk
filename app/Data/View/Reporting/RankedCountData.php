<?php

namespace App\Data\View\Reporting;

use Spatie\LaravelData\Data;

/** Satu batang di grafik berperingkat (W151): fakultas/prodi, tipe luaran, beban reviewer. */
final class RankedCountData extends Data
{
    public const LIMIT = 10;

    public function __construct(
        public string $label,
        public int $count,
        public ?string $href = null,
    ) {}

    /**
     * Terbesar dulu (seri: label menaik), maks LIMIT baris; sisanya dilipat menjadi "Lainnya".
     *
     * @param  list<self>  $rows
     * @return list<self>
     */
    public static function top(array $rows): array
    {
        usort($rows, fn (self $a, self $b): int => [$b->count, $a->label] <=> [$a->count, $b->label]);

        if (count($rows) <= self::LIMIT) {
            return $rows;
        }

        $rest = array_slice($rows, self::LIMIT);

        return [
            ...array_slice($rows, 0, self::LIMIT),
            new self('Lainnya', array_sum(array_map(fn (self $row): int => $row->count, $rest))),
        ];
    }
}
