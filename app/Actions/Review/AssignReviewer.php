<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\ReviewerAssignedNotification;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\UnderReview;
use App\Support\ConflictOfInterestGuard;
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

    /**
     * Ambang bawaan kalau skema tidak menyatakannya — dan kalau submission
     * tidak punya skema sama sekali. Bukan pengganti `min_reviewer_count`:
     * BR-REV-01 menegaskan ambangnya milik skema, jadi nilai ini hanya
     * berlaku saat memang tidak ada skema yang bisa ditanya.
     */
    private const DEFAULT_MIN_REVIEWERS = 2;

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

        $this->guardTenure($reviewer);
        $this->guardNotAlreadyAssigned($submission, $reviewer);

        return DB::transaction(function () use ($submission, $reviewer, $candidate): SubmissionReviewer {
            $assignment = SubmissionReviewer::create([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
                'evaluation_status' => 'pending',
                'status' => 'active',
            ]);

            $this->moveToReviewWhenMinimumReached($submission);

            // BR-REV-01 — reviewer diberi tahu. Beban tinggi (BR-REV-04)
            // sengaja tidak menghalangi: itu peringatan untuk Operator,
            // bukan penolakan.
            $candidate->notify(new ReviewerAssignedNotification($submission));

            return $assignment;
        });
    }

    /** BR-REV-09 — reviewer hanya bisa ditugaskan dalam rentang masa tugasnya. */
    private function guardTenure(Reviewer $reviewer): void
    {
        $now = now();

        $started = $reviewer->start_date === null || $reviewer->start_date->lessThanOrEqualTo($now);
        $notEnded = $reviewer->end_date === null || $reviewer->end_date->greaterThanOrEqualTo($now);

        if ($started && $notEnded) {
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
        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — penugasan reviewer tidak bisa memindahkannya."
            );
        }

        if ($status->equals(UnderReview::class)) {
            return;
        }

        $assigned = $submission->submissionReviewers()->where('status', 'active')->count();

        if ($assigned < $this->minimumReviewers($submission)) {
            return;
        }

        $status->transitionTo(UnderReview::class);
    }

    /**
     * Ambang dari `scheme.rules.min_reviewer_count`. Nilainya berasal dari
     * kolom JSONB, jadi bentuknya diperiksa — bukan di-cast — supaya angka
     * yang salah bentuk tidak diam-diam berubah jadi ambang yang keliru.
     */
    private function minimumReviewers(FormSubmission $submission): int
    {
        $rules = $submission->scheme?->rules;

        if (!is_array($rules)) {
            return self::DEFAULT_MIN_REVIEWERS;
        }

        $configured = $rules['min_reviewer_count'] ?? null;

        return is_int($configured) && $configured > 0
            ? $configured
            : self::DEFAULT_MIN_REVIEWERS;
    }
}
