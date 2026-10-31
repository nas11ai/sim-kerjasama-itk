<?php

namespace App\Data\View\Review;

use Spatie\LaravelData\Data;

/**
 * Satu kartu REVIEWER-03-01: penugasan reviewer pada satu pengajuan. `state` memilih aksi
 * kartu: `evaluation_pending`, `discussion_open`, atau `done`.
 */
final class AssignmentCardData extends Data
{
    public function __construct(
        public int $submissionId,
        public string $title,
        public string $leadName,
        public ?string $scheme,
        public ?string $dueDate,
        public string $state,
        public string $stateLabel,
    ) {}
}
