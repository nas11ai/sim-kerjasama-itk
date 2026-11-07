<?php

namespace App\Data\View\ResearchOutput;

use Spatie\LaravelData\Data;

/** Satu baris InfoGrid Detail Luaran: nilai tampilan, bukan nilai mentah metadata. */
final class OutputDetailFieldData extends Data
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
