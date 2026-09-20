<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;

/**
 * BR-REV-11 dan BR-REV-12 — siapa boleh melihat skor evaluasi, dan kapan. Dua
 * aturan yang sering tertukar padahal sumbunya berbeda: BR-REV-11 soal
 * **siapa**, BR-REV-12 soal **kapan**. Statis, preseden `RequiredFieldGuard`.
 */
final class ScoreVisibility
{
    /**
     * BR-REV-11 — skor rekan sesama reviewer, dan baru setelah seluruh
     * evaluasi selesai supaya penilaian tidak saling memengaruhi.
     */
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
        if ($assignment->reviewer->reviewer_type !== 'internal') {
            return false;
        }

        return self::everyEvaluationSettled($submission);
    }

    /**
     * BR-REV-12, dengan pengecualian BR-MON-09 — skor untuk pihak researcher,
     * baru terbuka setelah keputusan final.
     */
    public static function visibleToResearcher(User $user, FormSubmission $submission): bool
    {
        if ($submission->submitted_by !== $user->id) {
            return false;
        }

        // BR-MON-09 — pengecualian sengaja **sempit**, hanya laporan turunan:
        // monev formatif, koreksi arah mustahil kalau catatannya baru terbaca
        // setelah kegiatan usai. Awal tetap BR-REV-12, dijaga [MON-REVIEW-09].
        if ($submission->parent_submission_id !== null) {
            return self::anyEvaluationSubmitted($submission);
        }

        $status = $submission->status;

        // Keputusan final saja yang membuka — bukan sekadar "review selesai".
        return $status instanceof Approved || $status instanceof Rejected;
    }

    /**
     * BR-MON-09 — cukup satu reviewer menyelesaikan penilaiannya. Menunggu
     * seluruhnya akan menunda umpan balik yang justru gunanya cepat.
     */
    private static function anyEvaluationSubmitted(FormSubmission $submission): bool
    {
        return $submission->submissionReviewers()
            ->where('status', 'active')
            ->whereHas('reviewerFormAssignments.reviewFormResponse', function ($query): void {
                $query->where('status', 'submitted');
            })
            ->exists();
    }

    private static function everyEvaluationSettled(FormSubmission $submission): bool
    {
        return !$submission->submissionReviewers()
            ->where('status', 'active')
            ->whereNotIn('evaluation_status', SubmissionReviewer::SETTLED_EVALUATION)
            ->exists();
    }
}
