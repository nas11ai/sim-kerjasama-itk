<?php

namespace App\Enums;

/**
 * BR-IAM-04, BR-IAM-13 — status verifikasi NIDN pada UserProfile: satu dari
 * **tiga** sumbu keadaan akun, dan yang paling sering tertukar dengan dua
 * lainnya.
 */
enum VerificationStatus: string
{
    case Pending = 'pending';

    case Active = 'active';

    /**
     * Sengaja tidak terminal: email dan NIDN yang terlanjur terpakai membuat
     * pendaftaran ulang mustahil (BR-IAM-02), jadi penolakan permanen berarti
     * mematikan akun gara-gara satu digit salah ketik.
     */
    case Rejected = 'rejected';

    /**
     * BR-IAM-04/13 — hanya akun terverifikasi yang boleh submit. Sumbu ini
     * kerap tertukar: `User.is_active` menggerbang **login**, dan
     * `UserProfile::isComplete()` menggerbang **pembuatan pengajuan**.
     */
    public function canSubmit(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Active => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }
}
