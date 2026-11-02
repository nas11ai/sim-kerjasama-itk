<?php

namespace App\Data\View\Identity;

use Spatie\LaravelData\Data;

/** "Ringkasan" OPERATOR-08-03. */
final class UserSummaryData extends Data
{
    public function __construct(
        public int $activeLeadSubmissions,
        public int $runningReviews,
        public ?string $registeredAt,
    ) {}
}
