<?php

namespace App\Support;

use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * BR-IAM-03, BR-IAM-04, BR-IAM-05, BR-IAM-13 — tiga gerbang keadaan akun.
 *
 * Ketiganya sering dianggap satu, padahal menjawab pertanyaan berbeda dan
 * menggerbangi aksi berbeda:
 *
 *   | Sumbu                          | Menggerbang            |
 *   | ------------------------------ | ---------------------- |
 *   | `verification_status`          | **submit**             |
 *   | `User.is_active`               | **login**              |
 *   | `UserProfile::isComplete()`    | **membuat pengajuan**  |
 *
 * Satu user bisa lolos yang satu sambil tertahan yang lain: akun `pending`
 * dengan profil lengkap boleh menyiapkan DRAFT tapi tidak boleh mengirimnya;
 * akun terverifikasi dengan profil kosong tidak boleh memulai sama sekali.
 * Menyatukan ketiganya jadi satu kolom "user siap?" membuat salah satu dari
 * dua kasus itu berperilaku salah tanpa ada yang gagal mencolok.
 */
final class AccountStateGuard
{
    /**
     * BR-IAM-04, BR-IAM-13 — verifikasi menggerbang **komitmen**, bukan
     * persiapan. Tidak ada yang mengikat sampai submit, sementara verifikasi
     * NIDN makan waktu berhari-hari.
     *
     * @throws ValidationException
     */
    public static function assertVerified(User $user): void
    {
        if (self::verificationStatus($user) === VerificationStatus::Active) {
            return;
        }

        throw ValidationException::withMessages([
            'verification_status' => self::verificationStatus($user) === VerificationStatus::Rejected
                ? 'Verifikasi NIDN Anda ditolak. Hubungi Operator LPPM untuk koreksi sebelum mengirim pengajuan.'
                : 'Akun Anda belum terverifikasi. Pengajuan dapat disiapkan, tapi belum dapat dikirim.',
        ]);
    }

    /**
     * BR-IAM-03 — gerbang yang **berbeda** dari verifikasi. Implementasi yang
     * hanya memeriksa salah satunya lolos setengah kasus.
     *
     * @throws ValidationException
     */
    public static function assertProfileComplete(User $user): void
    {
        $profile = $user->userProfile;

        if ($profile !== null && $profile->isComplete()) {
            return;
        }

        throw ValidationException::withMessages([
            'profile' => 'Lengkapi profil Anda — nomor telepon, bidang keahlian, dan foto — '
                .'sebelum membuat pengajuan.',
        ]);
    }

    public static function isVerified(User $user): bool
    {
        return self::verificationStatus($user) === VerificationStatus::Active;
    }

    private static function verificationStatus(User $user): ?VerificationStatus
    {
        return $user->userProfile?->verification_status;
    }
}
