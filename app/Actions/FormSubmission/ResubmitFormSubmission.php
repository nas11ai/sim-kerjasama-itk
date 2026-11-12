<?php

namespace App\Actions\FormSubmission;

use App\Actions\Review\StartRecheckRound;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\FieldLabelSnapshot;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use App\Support\RequiredFieldGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class ResubmitFormSubmission
{
    use AsAction;

    /**
     * @throws AuthorizationException
     */
    public function handle(FormSubmission $submission, User $user): FormSubmission
    {
        $status = $this->guardResubmittable($submission, $user);

        // BR-SM-02 — gerbang dokumen berlaku juga di jalur resubmit. Jangkarnya
        // tetap created_at asli, jadi revisi tidak menarik field wajib baru ke
        // dalam pengajuan yang sudah berjalan (register #27).
        RequiredFieldGuard::assertComplete($submission);

        // BR-FE-35 — direkam ulang; transitionTo menyimpannya dalam update status yang sama.
        $submission->submitted_field_labels = FieldLabelSnapshot::run($submission);
        $status->transitionTo(Resubmitted::class);

        $submission->refresh();

        $this->returnToReviewQueue($submission);

        $tercatat = $submission;

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Pengajuan dikirim ulang setelah revisi', $tercatat, $user);

        return $tercatat;
    }

    /**
     * @throws AuthorizationException
     */
    private function guardResubmittable(FormSubmission $submission, User $user): SubmissionStatus
    {
        // BR-SM-04 — dijaga Policy; dipanggil di sini supaya aturannya juga
        // berlaku di jalur yang tidak lewat HTTP.
        if (!$user->can('resubmit', $submission)) {
            throw new AuthorizationException(
                'Hanya lead researcher yang bisa resubmit pengajuan ini.'
            );
        }

        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — tidak bisa di-resubmit."
            );
        }

        return $status;
    }

    /**
     * Dulu efek ini milik FormSubmission::updateStatusBasedOnReviews(),
     * yang dihapus karena memuat jalur auto-reject terlarang (ADR 0006).
     */
    private function returnToReviewQueue(FormSubmission $submission): void
    {
        // BR-SM-03 — pengajuan yang reviewer-nya masih terpasang langsung
        // masuk antrean review lagi; tidak perlu ditugaskan ulang. Tanpa ini
        // revisi berhenti di RESUBMITTED dan tidak pernah dinilai siapa pun.
        if ($submission->submissionReviewers()->where('status', 'active')->exists()) {
            $resubmitted = $submission->status;

            if ($resubmitted instanceof SubmissionStatus) {
                $resubmitted->transitionTo(UnderReview::class);
                $submission->refresh();

                // BR-REV-16 — reviewer bercatatan terbuka memeriksa revisinya di putaran baru.
                StartRecheckRound::run($submission);
            }
        }
    }
}
