<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/** BR-SM-15 — target luaran yang dijanjikan pengajuan. */
final class SubmissionTargetOutputData extends Data
{
    public function __construct(
        public string $type,
        public ?string $description,
    ) {}
}
