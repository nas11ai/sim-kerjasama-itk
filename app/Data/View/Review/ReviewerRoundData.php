<?php

namespace App\Data\View\Review;

use App\Enums\ReviewRoundKind;
use App\Models\ReviewRound;
use App\Models\SubmissionReviewer;
use Spatie\LaravelData\Data;

/**
 * BR-REV-16 — putaran berjalan dari sisi satu reviewer: label tombol Selesai, boleh tidaknya
 * menekannya, dan kemajuan putaran. Alasan memakai teks yang sama dengan FinishReviewRound.
 */
final class ReviewerRoundData extends Data
{
    public function __construct(
        public string $kind,
        public string $finishLabel,
        public bool $isParticipant,
        public bool $finished,
        public bool $canFinish,
        public ?string $reason,
        public ReviewRoundProgressData $progress,
    ) {}

    public static function of(ReviewRound $round, SubmissionReviewer $seat): self
    {
        $participant = $round->participants()->where('submission_reviewer_id', $seat->id)->first();
        $finished = $participant?->finished_at !== null;
        $reason = match (true) {
            $participant === null => 'Anda bukan peserta putaran review ini.',
            $finished => 'Anda sudah menyelesaikan putaran ini.',
            $round->kind === ReviewRoundKind::Assessment && !$seat->hasSettledEvaluation() => 'Kirim lembar penilaian lebih dulu.',
            default => null,
        };

        return new self(
            kind: $round->kind->value,
            finishLabel: $round->kind->finishLabel(),
            isParticipant: $participant !== null,
            finished: $finished,
            canFinish: $reason === null,
            reason: $reason,
            progress: ReviewRoundProgressData::of($round),
        );
    }
}
