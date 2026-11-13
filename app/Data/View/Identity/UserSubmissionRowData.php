<?php

namespace App\Data\View\Identity;

use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * Satu baris "Riwayat Submission" OPERATOR-08-03. `researchMembers` dimuat UserDetail hanya
 * milik user itu, jadi anggota pertama = perannya. `url` null bila draft atau tanpa view-all.
 */
final class UserSubmissionRowData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $role,
        public SubmissionStatusData $status,
        public ?string $url,
    ) {}

    public static function fromModel(FormSubmission $submission, User $user, bool $viewerManagesSubmissions): self
    {
        $memberRole = $submission->researchMembers->first()->role ?? ResearchMember::ROLE_MEMBER;

        return new self(
            id: $submission->id,
            title: $submission->displayTitle(),
            role: $submission->submitted_by === $user->id
                ? 'Ketua'
                : ResearchMember::ROLE_LABELS[$memberRole] ?? ResearchMember::ROLE_LABELS[ResearchMember::ROLE_MEMBER],
            status: SubmissionStatusData::of($submission),
            url: $viewerManagesSubmissions && $submission->is_submitted ? route('admin.submissions.show', $submission) : null,
        );
    }
}
