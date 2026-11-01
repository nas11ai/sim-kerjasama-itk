<?php

namespace App\Data\View\Dashboard;

use Spatie\LaravelData\Data;

/** Satu entri "Aktivitas Terbaru" dari jejak audit (BR-RPT-05); `at` ISO 8601. */
final class ActivityData extends Data
{
    public function __construct(
        public string $description,
        public ?string $actorName,
        public string $at,
    ) {}
}
