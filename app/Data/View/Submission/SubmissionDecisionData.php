<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/**
 * BR-REV-07 — penolakan selalu keputusan manual Operator. Pemutus dan waktunya dibaca
 * dari jejak audit; null bila penolakan terjadi sebelum audit dicatat.
 */
final class SubmissionDecisionData extends Data
{
    public function __construct(
        public ?string $reason,
        public ?string $decidedBy,
        public ?string $decidedAt,
    ) {}
}
