<?php

namespace App\Data\View\Dashboard;

use Spatie\LaravelData\Data;

/** Banner antrian Operator (SHARED-02-03): pekerjaan yang menunggu tindakannya. */
final class PendingWorkData extends Data
{
    public function __construct(
        public int $awaitingReviewer,
        public int $awaitingVerification,
    ) {}
}
