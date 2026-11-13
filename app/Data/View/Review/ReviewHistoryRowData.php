<?php

namespace App\Data\View\Review;

use App\Models\ReviewerFormAssignment;
use App\Models\SubmissionReviewer;
use Spatie\LaravelData\Data;

/**
 * Satu baris REVIEWER-03-02, per SubmissionReviewer. Relasi dimuat ReviewerHistory; tanpa skor
 * total dan tipe konteks karena keduanya tidak tersimpan (W22).
 */
final class ReviewHistoryRowData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $scheme,
        public ?string $context,
        public ?string $completedAt,
        public string $status,
        public string $statusLabel,
        public ?string $summaryUrl,
    ) {}

    public static function of(SubmissionReviewer $assignment): self
    {
        $submission = $assignment->formSubmission;
        $status = match (true) {
            $assignment->status === 'replaced' => 'replaced',
            $assignment->hasSettledEvaluation() => 'completed',
            default => 'pending',
        };
        $context = array_filter([$submission->formPhaseDetail?->formPhase?->title, $submission->submissionPeriod?->name]);

        return new self(
            id: $assignment->id,
            title: $submission->displayTitle(),
            scheme: $submission->scheme?->name,
            context: $context === [] ? null : implode(' · ', $context),
            completedAt: $assignment->reviewerFormAssignments
                ->map(fn (ReviewerFormAssignment $form) => $form->reviewFormResponse?->submitted_at)
                ->filter()
                ->max()
                ?->toIso8601String(),
            status: $status,
            statusLabel: ['replaced' => 'Digantikan', 'completed' => 'Selesai', 'pending' => 'Belum selesai'][$status],
            // BR-REV-10 — penugasan yang digantikan tidak lagi membuka layar ringkasan.
            summaryUrl: $status === 'replaced' ? null : route('reviewer.assignments.summary', $submission->id),
        );
    }
}
