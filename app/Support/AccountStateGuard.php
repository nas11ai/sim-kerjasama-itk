<?php

namespace App\Support;

use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * BR-IAM-03, BR-IAM-04, BR-IAM-05, BR-IAM-13 — dua dari tiga gerbang keadaan
 * akun; yang ketiga, `User.is_active`, menggerbangi login. Menyatukan ketiganya
 * jadi satu kolom "user siap?" membuat satu kasus salah tanpa gagal mencolok.
 */
final class AccountStateGuard
{
    /**
     * BR-IAM-04, BR-IAM-13 — verifikasi menggerbangi **submit**, bukan
     * persiapan: tidak ada yang mengikat sampai submit, sementara verifikasi
     * NIDN berhari-hari. Akun `pending` boleh menyiapkan DRAFT, belum mengirim.
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
     * BR-IAM-03 — profil lengkap menggerbangi **pembuatan** pengajuan: akun
     * terverifikasi berprofil kosong tidak boleh memulai sama sekali.
     * Memeriksa salah satu gerbang saja meloloskan setengah kasus.
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
