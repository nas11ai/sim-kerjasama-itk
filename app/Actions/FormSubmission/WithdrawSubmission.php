<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Withdrawn;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * BR-SM-11 — penarikan pengajuan.
 *
 * Tiga sifatnya, dan ketiganya disengaja:
 *
 *   1. **Bukan self-service.** Hanya pemegang `submissions.view-all` yang
 *      boleh menarik; pemilik pengajuan justru tidak.
 *   2. **Hanya dari APPROVED.** Pengajuan yang belum diputuskan dibatalkan
 *      lewat jalur lain, bukan ditarik.
 *   3. **Tidak dapat dibatalkan.** State machine dijaga asiklik — lihat
 *      `SubmissionStatus::config()`, yang tidak punya transisi keluar dari
 *      `Withdrawn`. Membalikkannya menuntut seluruh pembekuan hilir (siklus
 *      monev, luaran) ikut dipulihkan dengan benar, untuk peristiwa yang
 *      jarang. Kalau penarikan keliru, jalurnya pengajuan baru yang merujuk
 *      yang lama (register #20).
 *
 * Data historis tidak disentuh: anggaran, anggota, dan berkas tetap terbaca
 * setelah penarikan. Membekukan bukan menghapus.
 */
class WithdrawSubmission
{
    use AsAction;

    /**
     * @throws AuthorizationException
     */
    public function handle(FormSubmission $submission, User $operator): FormSubmission
    {
        // Kedua syarat — siapa dan dari status mana — dipegang Policy, supaya
        // objek `can` yang dikirim ke frontend jujur dan tombolnya tidak
        // muncul untuk aksi yang pasti ditolak (ADR 0015).
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

        $status->transitionTo(Withdrawn::class);

        return $submission->refresh();
    }
}
