<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\AccountDeactivatedNotification;
use App\Queries\Identity\DeactivationBlockers;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-05 dan BR-SM-09 — menonaktifkan user. Deaktivasi tidak menghapus apa pun supaya
 * jejak pengajuan lama tetap terbaca. Yang menghalangi cuma tanggung jawab yang belum
 * dialihkan: lead researcher tidak boleh meninggalkan pengajuan aktif tanpa penanggung jawab.
 */
class DeactivateUser
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(User $user): User
    {
        $blocking = DeactivationBlockers::run($user);

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'user_id' => $this->reason($blocking),
            ]);
        }

        $user->update(['is_active' => false]);

        // BR-IAM-15 — keputusan orang lain atas akunnya; ia tidak akan
        // menemukan sendiri selain lewat layar login yang menolaknya.
        Deliver::to($user, new AccountDeactivatedNotification);

        $tercatat = $user->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Akun dinonaktifkan', $tercatat);

        return $tercatat;
    }

    /**
     * Kalau jalan keluarnya belum ada (tidak ada co_investigator aktif), itu pun
     * dikatakan: Operator yang cuma diberi tahu "ditolak" akan mencoba berulang
     * tanpa tahu apa yang kurang.
     *
     * @param  Collection<int, FormSubmission>  $blocking
     */
    private function reason($blocking): string
    {
        $withoutSuccessor = $blocking->reject(
            fn (FormSubmission $s): bool => $s->getAttribute('has_active_co_investigator') === true
        );

        $base = "Masih ada {$blocking->count()} pengajuan aktif atas nama user ini. Pindahkan kepemilikannya lebih dulu.";

        if ($withoutSuccessor->isEmpty()) {
            return $base;
        }

        return $base.' '.$withoutSuccessor->count()
            .' di antaranya belum punya co_investigator aktif sebagai calon penerima.';
    }
}
