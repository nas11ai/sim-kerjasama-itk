<?php

namespace App\Data\View\Identity;

use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use Spatie\LaravelData\Data;

/**
 * SM-OWNER-03/04 — satu pengajuan aktif yang menghalangi deaktivasi ketuanya. Kolom
 * `has_active_co_investigator` diisi DeactivationBlockers; `manageUrl` hanya untuk view-all.
 */
final class DeactivationBlockerData extends Data
{
    public function __construct(
        public int $submissionId,
        public string $title,
        public SubmissionStatusData $status,
        public bool $hasActiveCoInvestigator,
        public ?string $manageUrl,
    ) {}

    public static function fromModel(FormSubmission $submission, bool $viewerManagesSubmissions): self
    {
        return new self(
            submissionId: $submission->id,
            title: $submission->form->title,
            status: SubmissionStatusData::of($submission),
            hasActiveCoInvestigator: $submission->getAttribute('has_active_co_investigator') === true,
            manageUrl: $viewerManagesSubmissions ? route('admin.submissions.show', $submission) : null,
        );
    }
}
