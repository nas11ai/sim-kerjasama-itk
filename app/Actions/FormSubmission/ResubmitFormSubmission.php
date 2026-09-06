<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\UnderReview;
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

        // BR-SM-02 — gerbang dokumen berlaku juga di jalur resubmit. Jangkarnya
        // tetap created_at asli, jadi revisi tidak menarik field wajib baru ke
        // dalam pengajuan yang sudah berjalan (register #27).
        RequiredFieldGuard::assertComplete($submission);

        $status->transitionTo(Resubmitted::class);

        $submission->refresh();

        // BR-SM-03 — pengajuan yang reviewer-nya masih terpasang langsung
        // masuk antrean review lagi; tidak perlu ditugaskan ulang. Tanpa ini
        // revisi berhenti di RESUBMITTED dan tidak pernah dinilai siapa pun.
        //
        // Dulu ini efek samping FormSubmission::updateStatusBasedOnReviews(),
        // yang dihapus karena memuat jalur auto-reject terlarang (ADR 0006).
        if ($submission->submissionReviewers()->where('status', 'active')->exists()) {
            $resubmitted = $submission->status;

            if ($resubmitted instanceof SubmissionStatus) {
                $resubmitted->transitionTo(UnderReview::class);
                $submission->refresh();
            }
        }

        return $submission;
    }
}
