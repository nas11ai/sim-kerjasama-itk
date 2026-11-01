<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Withdrawn;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * BR-SM-11 — penarikan pengajuan. Menarik adalah membekukan, bukan menghapus:
 * anggaran, anggota, dan berkas tetap terbaca setelah penarikan. Penarikan keliru
 * dijawab pengajuan baru yang merujuk yang lama (register #20), bukan dibalik.
 */
class WithdrawSubmission
{
    use AsAction;

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, User $operator, string $reason): FormSubmission
    {
        // Bukan self-service (hanya pemegang `submissions.view-all`, bukan pemiliknya) dan hanya
        // dari APPROVED — yang belum diputuskan dibatalkan lewat jalur lain. Keduanya di Policy
        // supaya objek `can` di frontend jujur dan tombolnya tidak muncul sia-sia (ADR 0015).
        if (!$operator->can('withdraw', $submission)) {
            throw new AuthorizationException(
                'Penarikan hanya dapat dilakukan Operator, dan hanya atas pengajuan yang sudah disetujui.'
            );
        }

        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — tidak bisa ditarik."
            );
        }

        // Super Admin lolos Policy lewat Gate::before; transisi yang tak terdaftar dijawab galat, bukan 500.
        if (!$status->canTransitionTo(Withdrawn::class)) {
            throw ValidationException::withMessages([
                'status' => 'Penarikan hanya untuk pengajuan yang sudah disetujui.',
            ]);
        }

        // **Tidak dapat dibatalkan**: `SubmissionStatus::config()` tidak punya transisi
        // keluar dari `Withdrawn`. Membalikkannya menuntut seluruh pembekuan hilir
        // (monev, luaran) ikut dipulihkan dengan benar, untuk peristiwa yang jarang.
        $status->transitionTo(Withdrawn::class);

        $tercatat = $submission->refresh();

        // BR-RPT-09 — satu Action, satu entri audit; alasannya ikut di entri itu (W26).
        AuditTrail::record('Pengajuan ditarik', $tercatat, $operator, new AuditChange(new: ['reason' => $reason]));

        return $tercatat;
    }
}
