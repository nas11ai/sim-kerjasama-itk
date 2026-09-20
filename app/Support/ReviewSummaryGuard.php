<?php

namespace App\Support;

use App\Models\SubmissionReviewer;
use Illuminate\Validation\ValidationException;

/**
 * BR-FE-06 — `ReviewSummary` baru boleh dibuat setelah evaluasi reviewer
 * selesai (`completed`) atau memang tidak diwajibkan (`not_required`). Statis,
 * mengikuti preseden `RequiredFieldGuard`.
 */
final class ReviewSummaryGuard
{
    /**
     * @throws ValidationException
     */
    public static function assertMayCreate(SubmissionReviewer $assignment): void
    {
        // Gerbangnya **status penugasan**, bukan jawaban evaluasi tersubmit:
        // penugasan `not_required` tak pernah punya jawaban, dan kalau
        // digerbang di sana ia tak bisa menutup ulir yang ia buka sendiri.
        if ($assignment->hasSettledEvaluation()) {
            return;
        }

        throw ValidationException::withMessages([
            'review_summary' => 'Selesaikan evaluasi Anda sebelum membuat catatan ringkasan.',
        ]);
    }
}
