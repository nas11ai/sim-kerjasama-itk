<?php

namespace App\Support;

use App\Models\SubmissionReviewer;
use Illuminate\Validation\ValidationException;

/**
 * BR-FE-06 — `ReviewSummary` baru boleh dibuat setelah evaluasi reviewer
 * selesai (`completed`) atau memang tidak diwajibkan (`not_required`).
 *
 * Gerbangnya **status penugasan**, bukan ada-tidaknya jawaban evaluasi yang
 * tersubmit. Keduanya mudah tertukar, dan bedanya nyata: penugasan
 * `not_required` tidak pernah punya jawaban tersubmit, tapi tetap berhak
 * membuka ringkasan — kalau digerbang pada jawaban, reviewer itu tidak akan
 * pernah bisa menutup ulir yang ia buka sendiri.
 *
 * `final class` statis mengikuti preseden `RequiredFieldGuard`.
 */
final class ReviewSummaryGuard
{
    /**
     * @throws ValidationException
     */
    public static function assertMayCreate(SubmissionReviewer $assignment): void
    {
        if ($assignment->hasSettledEvaluation()) {
            return;
        }

        throw ValidationException::withMessages([
            'review_summary' => 'Selesaikan evaluasi Anda sebelum membuat catatan ringkasan.',
        ]);
    }
}
