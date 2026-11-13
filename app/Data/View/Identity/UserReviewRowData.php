<?php

namespace App\Data\View\Identity;

use App\Data\View\Submission\Manage\ManagedReviewerData;
use App\Models\SubmissionReviewer;
use Spatie\LaravelData\Data;

/**
 * Satu baris "Riwayat Penilai" OPERATOR-08-03. BR-REV-10 — penugasan `replaced` adalah
 * sejarah, tetap terdaftar. `id` = penugasan, karena satu pengajuan bisa muncul dua kali.
 */
final class UserReviewRowData extends Data
{
    /**
     * @param  array{value: string, label: string}  $assignmentStatus
     * @param  array{value: string, label: string}  $evaluationStatus
     */
    public function __construct(
        public int $id,
        public int $submissionId,
        public string $title,
        public array $assignmentStatus,
        public array $evaluationStatus,
        public ?string $assignedAt,
    ) {}

    public static function fromModel(SubmissionReviewer $seat): self
    {
        $labels = ManagedReviewerData::EVALUATION_LABELS;

        return new self(
            id: $seat->id,
            submissionId: $seat->form_submission_id,
            title: $seat->formSubmission->displayTitle(),
            assignmentStatus: ['value' => $seat->status, 'label' => $seat->status === 'replaced' ? 'Digantikan' : 'Aktif'],
            evaluationStatus: ['value' => $seat->evaluation_status, 'label' => $labels[$seat->evaluation_status] ?? $labels['pending']],
            assignedAt: $seat->created_at?->toIso8601String(),
        );
    }
}
