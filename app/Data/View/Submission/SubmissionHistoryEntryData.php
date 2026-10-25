<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/** Satu entri jejak audit pengajuan untuk Timeline dan tab Riwayat. */
final class SubmissionHistoryEntryData extends Data
{
    public function __construct(
        public string $description,
        public ?string $actor,
        public ?string $at,
    ) {}
}
