<?php

namespace App\Data\View\ResearchOutput;

use App\Data\View\SubmissionStatusData;
use Spatie\LaravelData\Data;

/** Satu kartu RESEARCHER-06-01: pengajuan beserta luarannya dan hak tulis peneliti di atasnya. */
final class OutputSubmissionGroupData extends Data
{
    /** @param  list<OutputRowData>  $outputs */
    public function __construct(
        public int $submissionId,
        public string $title,
        public ?string $scheme,
        public SubmissionStatusData $status,
        public OutputWriteAccessData $access,
        public array $outputs,
    ) {}
}
