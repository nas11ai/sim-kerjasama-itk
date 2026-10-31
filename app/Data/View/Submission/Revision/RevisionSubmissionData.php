<?php

namespace App\Data\View\Submission\Revision;

use App\Data\View\SubmissionStatusData;
use Spatie\LaravelData\Data;

/** Kepala halaman revisi (RESEARCHER-02-05). */
final class RevisionSubmissionData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public SubmissionStatusData $status,
    ) {}
}
