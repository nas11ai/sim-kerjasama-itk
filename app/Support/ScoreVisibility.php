<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;

/**
 * BR-REV-11 dan BR-REV-12 — siapa boleh melihat skor evaluasi, dan kapan.
 *
 * Dua aturan yang sering tertukar padahal sumbunya berbeda:
 *
 *   BR-REV-11 — **siapa**: `reviewer_internal` boleh melihat skor rekannya,
 *               `reviewer_external` tidak pernah. Ditambah syarat waktu:
 *               hanya setelah seluruh evaluasi selesai, supaya penilaian
 *               tidak saling memengaruhi.
 *
 *   BR-REV-12 — **kapan**: researcher baru melihat skor setelah keputusan
 *               final. Dikecualikan untuk siklus monev oleh BR-MON-09, yang
 *               bersifat formatif — pengecualian itu sengaja sempit dan
 *               tidak diterapkan di sini.
 *
 * `final class` statis mengikuti preseden `RequiredFieldGuard`.
 */
final class ScoreVisibility
{
    /** BR-REV-11 — skor rekan sesama reviewer. */
    public static function peerScoresVisibleTo(User $user, FormSubmission $submission): bool
    {
        $assignment = $submission->submissionReviewers()
            ->where('status', 'active')
            ->whereHas('reviewer', fn ($q) => $q->where('user_id', $user->id))
            ->with('reviewer')
            ->first();

        if ($assignment === null) {
            return false;
        }

        // Bukan soal waktu: reviewer eksternal tertutup kapan pun, bahkan
        // setelah seluruh evaluasi masuk.
        if ($assignment->reviewer?->reviewer_type !== 'internal') {
            return false;
        }

        return self::everyEvaluationSettled($submission);
    }

    /** BR-REV-12 — skor untuk pihak researcher. */
    public static function visibleToResearcher(User $user, FormSubmission $submission): bool
    {
        if ($submission->submitted_by !== $user->id) {
            return false;
        }

        $status = $submission->status;

        // Keputusan final saja yang membuka — bukan sekadar "review selesai".
        return $status instanceof Approved || $status instanceof Rejected;
    }

    private static function everyEvaluationSettled(FormSubmission $submission): bool
    {
        return !$submission->submissionReviewers()
            ->where('status', 'active')
            ->whereNotIn('evaluation_status', SubmissionReviewer::SETTLED_EVALUATION)
            ->exists();
    }
}
