<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\ReviewerAssignedNotification;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use App\Support\ConflictOfInterestGuard;
use App\Support\Notification\Deliver;
use App\Support\Review\MinimumReviewers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * `AsAction::run()` tidak membawa tipe kembalian, jadi dideklarasikan di sini
 * — pemanggilnya berhak tahu apa yang ia dapat tanpa harus menebak.
 *
 * @method static SubmissionReviewer run(FormSubmission $submission, Reviewer $reviewer)
 */
class AssignReviewer
{
    use AsAction;

    public function handle(FormSubmission $submission, Reviewer $reviewer): SubmissionReviewer
    {
        // BR-IAM-11 — reviewer ditunjuk dari akun yang sudah ada, jadi record
        // tanpa User adalah cacat data. Gagal keras di sini lebih baik daripada
        // melewati pemeriksaan konflik kepentingan diam-diam.
        $candidate = $reviewer->user;

        if (!$candidate instanceof User) {
            throw new RuntimeException(
                "Reviewer {$reviewer->id} tidak tertaut ke User — penugasan dibatalkan."
            );
        }

        // BR-REV-02 — satu implementasi, dipanggil dari sini dan dari
        // penambahan anggota. Jangan diulang isinya di sini.
        ConflictOfInterestGuard::assertNone($candidate, $submission);

        $this->guardAccountActive($candidate);
        $this->guardTenure($reviewer);
        $this->guardNotAlreadyAssigned($submission, $reviewer);

        $tercatat = DB::transaction(fn (): SubmissionReviewer => $this->persist($submission, $reviewer, $candidate));

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Reviewer ditugaskan', $tercatat);

        return $tercatat;
    }

    private function persist(FormSubmission $submission, Reviewer $reviewer, User $candidate): SubmissionReviewer
    {
        $assignment = SubmissionReviewer::create([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'evaluation_status' => 'pending',
            'status' => 'active',
        ]);

        $this->moveToReviewWhenMinimumReached($submission);

        // BR-REV-16 — reviewer baru ikut putaran penilaian (laporan monev dikecualikan di sana).
        JoinReviewRound::run($assignment);

        // BR-REV-01 — reviewer diberi tahu. Beban tinggi (BR-REV-04)
        // sengaja tidak menghalangi: itu peringatan untuk Operator,
        // bukan penolakan.
        Deliver::to($candidate, new ReviewerAssignedNotification($submission));

        return $assignment;
    }

    /** BR-IAM-05, IAM-DEACT-04 — akun nonaktif tidak menerima penugasan baru, termasuk sebagai pengganti. */
    private function guardAccountActive(User $candidate): void
    {
        if ($candidate->is_active) {
            return;
        }

        throw ValidationException::withMessages([
            'reviewer_id' => 'Akun reviewer ini nonaktif, jadi tidak dapat ditugaskan.',
        ]);
    }

    /** BR-REV-09 — hanya dalam masa tugas; inklusif per hari, end_date hari terakhir. */
    private function guardTenure(Reviewer $reviewer): void
    {
        if ($reviewer->isWithinTenure()) {
            return;
        }

        throw ValidationException::withMessages([
            'reviewer_id' => 'Reviewer ini berada di luar masa tugasnya, jadi tidak dapat ditugaskan.',
        ]);
    }

    /** BR-REV-03 — satu reviewer, satu penugasan per siklus. */
    private function guardNotAlreadyAssigned(FormSubmission $submission, Reviewer $reviewer): void
    {
        $exists = $submission->submissionReviewers()
            ->where('reviewer_id', $reviewer->id)
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'reviewer_id' => 'Reviewer ini sudah ditugaskan pada siklus review ini.',
        ]);
    }

    /**
     * BR-REV-01 — begitu jumlah minimum tercapai, submission berpindah ke
     * UNDER_REVIEW. Ambangnya dibaca dari `scheme.rules.min_reviewer_count`,
     * bukan konstanta: skema yang berbeda menuntut jumlah yang berbeda.
     */
    private function moveToReviewWhenMinimumReached(FormSubmission $submission): void
    {
        // BR-REV-01 soal **pengajuan**, bukan turunannya. Laporan monev punya
        // lifecycle sendiri (BR-MON-02), tak lewat UNDER_REVIEW.
        if ($submission->parent_submission_id !== null) {
            // Transisi paksa melempar TransitionNotFound dari DRAFT,
            // menggagalkan penugasan sah.
            return;
        }

        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — penugasan reviewer tidak bisa memindahkannya."
            );
        }

        if ($status->equals(UnderReview::class)) {
            return;
        }

        $assigned = $submission->activeSubmissionReviewers()->count();

        if ($assigned < MinimumReviewers::for($submission)) {
            return;
        }

        $status->transitionTo(UnderReview::class);
    }
}
