<?php

namespace App\Actions\Identity;

use App\Enums\VerificationStatus;
use App\Models\UserProfile;
use App\Notifications\NidnVerifiedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-04, BR-IAM-13, BR-IAM-15 — Operator memverifikasi NIDN pendaftar.
 *
 * @method static UserProfile run(UserProfile $profile)
 */
class VerifyNidn
{
    use AsAction;

    public function handle(UserProfile $profile): UserProfile
    {
        // Bisa dipanggil atas akun `pending` maupun `rejected`: penolakan sengaja tidak terminal.
        // Email dan NIDN yang terlanjur terpakai membuat pendaftaran ulang mustahil (BR-IAM-02),
        // jadi penolakan permanen berarti mematikan akun gara-gara satu digit salah ketik.
        $profile->update([
            'verification_status' => VerificationStatus::Active->value,
            // Alasan penolakan sebelumnya dibersihkan: ia sudah tidak berlaku, dan menyisakannya
            // membuat layar pendaftar mengeluhkan keadaan yang sudah lewat.
            'rejection_reason' => null,
        ]);

        // BR-IAM-15 — keputusan orang lain atas akun seseorang memberi tahu
        // orang itu; kemampuannya berubah tanpa ia melakukan apa pun.
        Deliver::to($profile->user, new NidnVerifiedNotification);

        $tercatat = $profile->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Verifikasi NIDN disetujui', $tercatat);

        return $tercatat;
    }
}
