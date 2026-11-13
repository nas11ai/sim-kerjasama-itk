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
 * Penolakan tidak terminal: `VerifyNidn` tetap bisa dipanggil setelahnya.
 *
 * @method static UserProfile run(UserProfile $profile, string $reason)
 */
class RejectNidnVerification
{
    use AsAction;

    /** Dibaca RejectedAt sebagai "Ditolak pada" (W53). */
    public const AUDIT_DESCRIPTION = 'Verifikasi NIDN ditolak';

    /**
     * @throws ValidationException
     */
    public function handle(UserProfile $profile, string $reason): UserProfile
    {
        // Alasannya **wajib**, dan itu bukan formalitas: hanya itu yang dipunyai pendaftar untuk
        // memperbaiki datanya. Tanpa alasan ia cuma bisa menebak — mendaftar ulang mustahil
        // karena email dan NIDN-nya sudah terpakai.
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi, pendaftar perlu tahu apa yang harus diperbaiki.',
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
        AuditTrail::record(self::AUDIT_DESCRIPTION, $tercatat);

        return $tercatat;
    }
}
