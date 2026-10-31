<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/** Periode terbuka di langkah 1 beserta form yang boleh dimulai peneliti di sana. */
final class StartPeriodOptionData extends Data
{
    /** @param  list<StartFormOptionData>  $forms */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $closesAt,
        public array $forms,
    ) {}
}
