<?php

namespace App\Data\View\Review;

use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use Spatie\LaravelData\Data;

/** Kepala pengajuan di layar kerja reviewer (REVIEWER-03-03/03-04), baca-saja. */
final class EvaluationSubmissionData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $scheme,
        public ?string $period,
        public string $leadName,
        public int $memberCount,
        public SubmissionStatusData $status,
    ) {}

    public static function of(FormSubmission $submission): self
    {
        $submission->loadMissing(['form:id,title', 'scheme:id,name', 'submissionPeriod:id,name', 'submittedBy:id,name']);

        return new self(
            id: $submission->id,
            title: $submission->form->title,
            scheme: $submission->scheme?->name,
            period: $submission->submissionPeriod?->name,
            leadName: $submission->submittedBy->name,
            // Ketua ditambah anggota aktif.
            memberCount: 1 + $submission->researchMembers()->where('status', 'active')->count(),
            status: SubmissionStatusData::of($submission),
        );
    }
}
