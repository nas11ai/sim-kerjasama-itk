<?php

namespace App\Actions\Identity;

use App\Enums\VerificationStatus;
use App\Models\UserProfile;
use App\Notifications\NidnVerificationRejectedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-13 — Operator menolak verifikasi NIDN.
 *
 * Alasannya **wajib**, dan itu bukan formalitas: alasan itu satu-satunya yang
 * dipunyai pendaftar untuk memperbaiki datanya. Tanpa alasan, satu-satunya
 * jalan keluarnya adalah menebak — sementara mendaftar ulang mustahil karena
 * email dan NIDN-nya sudah terpakai.
 *
 * Penolakan tidak terminal: `VerifyNidn` tetap bisa dipanggil setelahnya.
 *
 * @method static UserProfile run(UserProfile $profile, string $reason)
 */
class RejectNidnVerification
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(UserProfile $profile, string $reason): UserProfile
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi — pendaftar perlu tahu apa yang harus diperbaiki.',
            ]);
        }

        $profile->update([
            'verification_status' => VerificationStatus::Rejected->value,
            'rejection_reason' => trim($reason),
        ]);

        // BR-IAM-13/BR-IAM-15 — alasannya ikut terkirim, bukan sekadar fakta
        // penolakannya.
        Deliver::to($profile->user, new NidnVerificationRejectedNotification(trim($reason)));

        $tercatat = $profile->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Verifikasi NIDN ditolak', $tercatat);

        return $tercatat;
    }
}
