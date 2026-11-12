<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/** K-3 (b) — pengajuan yang reviewer-nya terkunci Batas Evaluasi; dibuka ke detailnya untuk perpanjangan. */
final class PastDeadlineSubmissionData extends Data
{
    public function __construct(
        public int $submissionId,
        public string $researcherName,
    ) {}
}
