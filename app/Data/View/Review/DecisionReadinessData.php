<?php

namespace App\Data\View\Review;

use Spatie\LaravelData\Data;

/**
 * BR-REV-05, BR-REV-07 — apakah Operator boleh memutuskan pengajuan ini sekarang, dan bila belum,
 * apa yang menahannya. `approvable` lebih ketat dari `ready`: Setujui juga menunggu catatan ditutup.
 */
final class DecisionReadinessData extends Data
{
    /** @param list<string> $blockers */
    public function __construct(
        public bool $ready,
        public bool $approvable,
        public array $blockers,
        public int $openThreadCount,
        public ?ReviewRoundProgressData $round,
    ) {}
}
