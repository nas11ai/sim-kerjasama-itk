<?php

namespace App\Data\View\Submission;

use App\Data\View\SubmissionStatusData;
use Spatie\LaravelData\Data;

/**
 * Kepala RESEARCHER-02-03. `viewerRole` hanya untuk label; tombol tetap membaca `can`.
 * `editUrl` menaut wizard pengajuan (S2b); RevisionPage menyusul di S2c.
 */
final class SubmissionDetailData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $scheme,
        public ?string $period,
        public ?string $trl,
        public string $leadName,
        public SubmissionStatusData $status,
        public ?string $submittedAt,
        public bool $isArchived,
        public string $viewerRole,
        public string $viewerRoleLabel,
        public ?string $editUrl,
    ) {}
}
