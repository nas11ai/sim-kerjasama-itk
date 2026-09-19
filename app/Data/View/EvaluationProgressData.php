<?php

namespace App\Data\View;

/**
 * Bentuk statistik evaluasi untuk layar daftar dan detail pengajuan. Kunci
 * toArray() sama persis dengan getEvaluationStats() lama supaya layarnya tetap.
 */
final class EvaluationProgressData
{
    public function __construct(
        public readonly int $totalReviewers,
        public readonly int $completed,
        public readonly int $pending,
        public readonly int $notRequired,
        public readonly int $completionPercentage,
        public readonly bool $allCompleted,
    ) {}

    /** @return array<string, int|bool> */
    public function toArray(): array
    {
        return [
            'total_reviewers' => $this->totalReviewers,
            'completed_evaluations' => $this->completed,
            'pending_evaluations' => $this->pending,
            'no_evaluation_required' => $this->notRequired,
            'completion_percentage' => $this->completionPercentage,
            'all_completed' => $this->allCompleted,
        ];
    }
}
