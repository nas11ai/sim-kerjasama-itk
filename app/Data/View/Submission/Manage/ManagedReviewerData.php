<?php

namespace App\Data\View\Submission\Manage;

use App\Models\SubmissionReviewer;
use Spatie\LaravelData\Data;

/**
 * Satu reviewer di panel OPERATOR-02-02. Yang `replaced` tetap terdaftar sebagai sejarah
 * (BR-REV-10, REV-REASSIGN-01); `type` mentah (`internal`/`external`), labelnya milik layar.
 */
final class ManagedReviewerData extends Data
{
    public const EVALUATION_LABELS = [
        'pending' => 'Evaluasi berjalan',
        'completed' => 'Selesai',
        'not_required' => 'Tanpa form evaluasi',
    ];

    public function __construct(
        public int $reviewerId,
        public string $name,
        public ?string $nidn,
        public string $type,
        public bool $replaced,
        public string $evaluationLabel,
        public bool $accountActive,
    ) {}

    public static function fromModel(SubmissionReviewer $seat): self
    {
        $user = $seat->reviewer->user;

        return new self(
            reviewerId: $seat->reviewer_id,
            name: $user->name ?? 'Reviewer',
            nidn: $user?->userProfile?->nidn,
            type: $seat->reviewer->reviewer_type,
            replaced: $seat->status === 'replaced',
            evaluationLabel: self::EVALUATION_LABELS[$seat->evaluation_status] ?? self::EVALUATION_LABELS['pending'],
            // BR-IAM-05, IAM-DEACT-11 — penugasan tetap; layar menandainya perlu diganti (BR-REV-10).
            accountActive: $user?->is_active === true,
        );
    }
}
