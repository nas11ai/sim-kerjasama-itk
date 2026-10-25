<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Rejected;
use App\States\Submission\SubmissionStatus;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * BR-REV-07 dan BR-REV-14 — **satu-satunya** jalur menuju REJECTED.
 *
 * Penolakan tidak pernah otomatis (ADR 0006): skor serendah apa pun tidak menolak.
 * `EvaluateSubmissionOutcome` tidak punya cabang ke sini, dijaga `[REV-OUTCOME-08]`.
 *
 * @method static FormSubmission run(FormSubmission $submission, User $operator, string $reason)
 */
class RejectSubmission
{
    use AsAction;

    /** Entri terbaru dengan deskripsi ini adalah "Diputuskan oleh/Tanggal" di detail. */
    public const AUDIT_DESCRIPTION = 'Pengajuan ditolak';

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, User $operator, string $reason): FormSubmission
    {
        if (!$operator->can('reject', $submission)) {
            throw new AuthorizationException(
                'Penolakan hanya dapat dilakukan Operator atas pengajuan yang sedang direview.'
            );
        }

        $reason = trim($reason);

        // Yang menolak adalah orang, dengan nama dan alasan.
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi — peneliti berhak tahu dasar keputusannya.',
            ]);
        }

        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — tidak bisa ditolak."
            );
        }

        $submission->update(['rejection_reason' => $reason]);
        $status->transitionTo(Rejected::class);

        $tercatat = $submission->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record(self::AUDIT_DESCRIPTION, $tercatat, $operator);

        return $tercatat;
    }
}
