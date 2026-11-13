<?php

namespace App\Data\View\Submission\Manage;

use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Satu baris OPERATOR-02-01. `submittedAt` dan `reviewerCount` diisi kolom tambahan
 * OperatorSubmissionList (subquery jejak audit dan `withCount` penugasan aktif).
 */
final class OperatorSubmissionRowData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $leadName,
        public ?string $organization,
        public ?string $scheme,
        public SubmissionStatusData $status,
        public ?string $submittedAt,
        public int $reviewerCount,
    ) {}

    public static function fromModel(FormSubmission $submission): self
    {
        $submittedAt = $submission->getAttribute('submitted_at');
        $reviewerCount = $submission->getAttribute('active_reviewers_count');

        return new self(
            id: $submission->id,
            title: $submission->displayTitle(),
            leadName: $submission->submittedBy->name,
            organization: $submission->submittedBy->organization?->name,
            scheme: $submission->scheme?->name,
            status: SubmissionStatusData::of($submission),
            submittedAt: is_string($submittedAt) ? CarbonImmutable::parse($submittedAt)->toIso8601String() : null,
            reviewerCount: is_int($reviewerCount) ? $reviewerCount : 0,
        );
    }
}
