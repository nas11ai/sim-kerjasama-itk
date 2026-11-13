<?php

namespace App\Actions\FormSubmission;

use App\Enums\SessionEndReason;
use App\Events\Identity\UserSessionsEnded;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\AccountDeactivatedNotification;
use App\Queries\Identity\DeactivationBlockers;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Identity\SessionRegistry;
use App\Support\Notification\Deliver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        // BR-IAM-05, IAM-DEACT-09 — remember-me lama tak berlaku lagi dan sesi database diakhiri;
        // sesi lain (driver apa pun) ditutup EnsureAccountActive di request berikutnya.
        $user->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
        $sessionsEnded = SessionRegistry::of($user)->delete();
        // BR-IAM-21, K7 — dorongan; penegakan tetap di server.
        UserSessionsEnded::dispatch($user->id, SessionEndReason::Deactivated);

        // BR-IAM-15 — keputusan orang lain atas akunnya; ia tidak akan
        // menemukan sendiri selain lewat layar login yang menolaknya.
        Deliver::to($user, new AccountDeactivatedNotification);

        $tercatat = $user->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Akun dinonaktifkan', $tercatat, null, new AuditChange(new: ['sessions_ended' => $sessionsEnded]));

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

        $base = "Masih ada {$blocking->count()} pengajuan aktif atas nama pengguna ini. Pindahkan kepemilikannya lebih dulu.";

        if ($withoutSuccessor->isEmpty()) {
            return $base;
        }

        return $base.' '.$withoutSuccessor->count()
            .' di antaranya belum punya co_investigator aktif sebagai calon penerima.';
    }
}
