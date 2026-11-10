<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/**
 * BR-REV-05, BR-REV-07 — persetujuan dan penolakan selalu keputusan manual Operator. Pemutus dan
 * waktunya dibaca dari jejak audit; null bila keputusan terjadi sebelum audit dicatat.
 */
final class SubmissionDecisionData extends Data
{
    /** @param  'approved'|'rejected'  $outcome  `reason` hanya ada pada penolakan. */
    public function __construct(
        public string $outcome,
        public ?string $reason,
        public ?string $decidedBy,
        public ?string $decidedAt,
    ) {}
}
