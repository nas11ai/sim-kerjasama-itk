<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * BR-REV-05 dan BR-REV-06 — satu-satunya tempat hasil review diputuskan. Jalur ke REJECTED
 * **tidak** ada di sini dan tidak boleh ditambahkan (BR-REV-07, BR-REV-14, `[REV-OUTCOME-08]`):
 * penolakan hanya lewat aksi eksplisit Operator — [ADR 0006](docs/adr/0006-rejection-is-manual-only.md).
 */
class EvaluateSubmissionOutcome
{
    use AsAction;

    /** Status yang punya jalur ke UNDER_REVIEW, tempat APPROVED/NEEDS_REVISION diputuskan. */
    private const AWAITING_DECISION = [Submitted::class, UnderReview::class, Resubmitted::class];

    public function handle(FormSubmission $submission): FormSubmission
    {
        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — hasil review tidak bisa dievaluasi."
            );
        }

        // Hanya status yang menunggu keputusan yang bisa diputuskan; pemicunya (evaluasi masuk,
        // ulir ditutup) bisa datang di status lain, mis. pengganti mengevaluasi saat NEEDS_REVISION.
        if (!$status->equals(...self::AWAITING_DECISION)) {
            return $submission;
        }

        // Arti "selesai" dipegang `SubmissionReviewer::SETTLED_EVALUATION` — satu kosakata,
        // dipakai juga oleh `ReviewSummaryGuard` (BR-FE-06).
        if (!$this->everyEvaluationSettled($submission)) {
            return $submission;
        }

        // ADR 0017 — persetujuan hampa hanya sah kalau fase memang dinyatakan tidak membutuhkan
        // review. Fase yang butuh review tapi belum punya reviewer bukan "semua selesai",
        // melainkan salah konfigurasi: ia menggantung di SUBMITTED sampai Operator menugaskan.
        if ($this->hasNoReviewers($submission) && $this->phaseNeedsReview($submission)) {
            return $submission;
        }

        // Dua transisi otomatis bergantung pada kombinasi yang sama: seluruh
        // evaluasi selesai, lalu ada atau tidaknya `ReviewSummary` berstatus `open`
        // — tidak ada → APPROVED (BR-REV-05), ada → NEEDS_REVISION (BR-REV-06).
        $this->settleInto(
            $submission,
            $status,
            $this->hasOpenSummary($submission) ? NeedsRevision::class : Approved::class
        );

        $tercatat = $submission;

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Keputusan hasil review ditetapkan', $tercatat);

        return $tercatat;
    }

    private function everyEvaluationSettled(FormSubmission $submission): bool
    {
        return !$submission->submissionReviewers()
            ->where('status', 'active')
            ->whereNotIn('evaluation_status', SubmissionReviewer::SETTLED_EVALUATION)
            ->exists();
    }

    private function hasNoReviewers(FormSubmission $submission): bool
    {
        return !$submission->submissionReviewers()->where('status', 'active')->exists();
    }

    private function phaseNeedsReview(FormSubmission $submission): bool
    {
        $detail = $submission->formPhaseDetail;

        // Tanpa FormPhaseDetail, tidak ada yang menyatakan fase ini boleh
        // lewat tanpa review — jadi diperlakukan sebagai butuh review.
        if ($detail === null) {
            return true;
        }

        return $detail->needs_review;
    }

    private function hasOpenSummary(FormSubmission $submission): bool
    {
        return $submission->reviewSummaries()->where('status', 'open')->exists();
    }

    /**
     * `APPROVED` dan `NEEDS_REVISION` hanya bisa dimasuki dari `UNDER_REVIEW`. Submission yang
     * masih `SUBMITTED` — kasus hampa ADR 0017 — dilewatkan dulu ke `UNDER_REVIEW`, bukan dengan
     * melebarkan state machine: satu pintu ke status terminal membuat `[REV-OUTCOME-08]` dipercaya.
     *
     * @param  class-string<SubmissionStatus>  $target
     */
    private function settleInto(FormSubmission $submission, SubmissionStatus $status, string $target): void
    {
        if (!$status->equals(UnderReview::class)) {
            $status->transitionTo(UnderReview::class);

            $refreshed = $submission->refresh()->status;

            if (!$refreshed instanceof SubmissionStatus) {
                throw new RuntimeException(
                    "FormSubmission {$submission->id} kehilangan status setelah masuk UNDER_REVIEW."
                );
            }

            $status = $refreshed;
        }

        $status->transitionTo($target);
    }
}
