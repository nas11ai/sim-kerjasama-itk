<?php

namespace App\Actions\Identity;

use App\Enums\VerificationStatus;
use App\Models\UserProfile;
use App\Notifications\NidnVerifiedNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-04, BR-IAM-13, BR-IAM-15 — Operator memverifikasi NIDN pendaftar.
 *
 * Bisa dipanggil atas akun `pending` maupun `rejected`: penolakan sengaja
 * tidak terminal. Email dan NIDN yang terlanjur terpakai membuat pendaftaran
 * ulang mustahil (BR-IAM-02), jadi penolakan permanen berarti mematikan akun
 * gara-gara satu digit salah ketik.
 *
 * Alasan penolakan sebelumnya dibersihkan — ia sudah tidak berlaku, dan
 * menyisakannya membuat layar pendaftar menampilkan keluhan atas keadaan yang
 * sudah lewat.
 *
 * @method static UserProfile run(UserProfile $profile)
 */
class VerifyNidn
{
    use AsAction;

    public function handle(UserProfile $profile): UserProfile
    {
        $profile->update([
            'verification_status' => VerificationStatus::Active->value,
            'rejection_reason' => null,
        ]);

        // BR-IAM-15 — keputusan orang lain atas akun seseorang memberi tahu
        // orang itu; kemampuannya berubah tanpa ia melakukan apa pun.
        $profile->user?->notify(new NidnVerifiedNotification);

        return $profile->refresh();
    }
}
