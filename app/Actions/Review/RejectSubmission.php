<?php

namespace App\Actions\Review;

use App\Actions\Concerns\SettlesThroughUnderReview;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\ProposalRejectedNotification;
use App\Queries\Review\DecisionReadiness;
use App\States\Submission\Rejected;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-07 dan BR-REV-14 — **satu-satunya** jalur menuju REJECTED.
 *
 * Penolakan tidak pernah otomatis (ADR 0006): skor serendah apa pun tidak menolak.
 * `ConcludeReviewRound` tidak punya cabang ke sini, dijaga `[REV-OUTCOME-08]`.
 *
 * @method static FormSubmission run(FormSubmission $submission, User $operator, string $reason)
 */
class RejectSubmission
{
    use AsAction;
    use SettlesThroughUnderReview;

    /** Entri terbaru dengan deskripsi ini adalah "Diputuskan oleh/Tanggal" di detail. */
    public const AUDIT_DESCRIPTION = 'Pengajuan ditolak';

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, User $operator, string $reason): FormSubmission
    {
        // Satu hasil kesiapan untuk Policy dan pemeriksaan ulang di bawah.
        $readiness = DecisionReadiness::run($submission);

        if (!$operator->can('reject', [$submission, $readiness])) {
            throw new AuthorizationException(
                'Penolakan hanya dapat dilakukan Operator atas pengajuan yang siap diputuskan.'
            );
        }

        $reason = trim($reason);

        // Yang menolak adalah orang, dengan nama dan alasan.
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi, peneliti berhak tahu dasar keputusannya.',
            ]);
        }

        // Super Admin lolos Policy lewat Gate::before (ADR 0015); syarat siap diputuskan dibaca ulang
        // supaya keputusan tetap menunggu putaran selesai (BR-REV-07), dijawab galat, bukan 500.
        if (!$readiness->ready) {
            throw ValidationException::withMessages([
                'status' => 'Penolakan hanya untuk pengajuan yang siap diputuskan.',
            ]);
        }

        $submission->update(['rejection_reason' => $reason]);
        $this->settleInto($submission, Rejected::class);

        $tercatat = $submission->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record(self::AUDIT_DESCRIPTION, $tercatat, $operator);

        // BR-REV-07 — peneliti utama diberi tahu beserta alasannya.
        Deliver::to($tercatat->submittedBy, new ProposalRejectedNotification($tercatat));

        return $tercatat;
    }
}
