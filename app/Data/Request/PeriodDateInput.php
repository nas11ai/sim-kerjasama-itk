<?php

namespace App\Data\Request;

use App\Enums\DeadlineType;

/** Satu batas akhir periode: jenis dan siklusnya (BR-FE-25), lalu tanggalnya. */
final class PeriodDateInput
{
    public function __construct(
        public readonly DeadlineType $type,
        public readonly ?int $cycle,
        public readonly string $date,
    ) {}

    /** Kunci (jenis, siklus) — satu periode punya paling banyak satu tanggal per kunci. */
    public function key(): string
    {
        return self::keyOf($this->type, $this->cycle);
    }

    public static function keyOf(DeadlineType $type, ?int $cycle): string
    {
        return $type->value.':'.($cycle ?? '');
    }
}
