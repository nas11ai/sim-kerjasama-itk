<?php

namespace App\Data\View\Submission;

use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Satu baris RESEARCHER-02-01. `submittedAt` diisi subquery jejak audit oleh
 * ResearcherSubmissionList; tabel pengajuan tidak menyimpan tanggal submit.
 */
final class SubmissionRowData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $scheme,
        public ?string $period,
        public SubmissionStatusData $status,
        public ?string $submittedAt,
        public bool $isLead,
        public bool $isArchived,
    ) {}

    public static function fromModel(FormSubmission $submission, User $viewer): self
    {
        $submittedAt = $submission->getAttribute('submitted_at');

        return new self(
            id: $submission->id,
            title: $submission->displayTitle(),
            scheme: $submission->scheme?->name,
            period: $submission->submissionPeriod?->name,
            status: SubmissionStatusData::of($submission),
            submittedAt: is_string($submittedAt) ? CarbonImmutable::parse($submittedAt)->toIso8601String() : null,
            isLead: $submission->submitted_by === $viewer->id,
            // BR-SM-10 — draft dari periode tertutup berubah read-only.
            isArchived: $submission->resolveIsArchived(),
        );
    }
}
