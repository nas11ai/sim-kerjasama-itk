<?php

namespace App\Enums;

/**
 * RT1, W170 — alasan sesi seorang user berakhir dari luar. Nilainya ikut dalam query
 * `?sesi=` halaman login; teksnya tetap, jadi query palsu hanya bisa memilih salah satunya.
 */
enum SessionEndReason: string
{
    /** BR-IAM-05, IAM-DEACT-09 */
    case Deactivated = 'nonaktif';

    /** BR-IAM-21, IAM-SESSION-01 */
    case Ended = 'diakhiri';

    public function notice(): string
    {
        return match ($this) {
            self::Deactivated => 'Akun Anda dinonaktifkan. Hubungi LPPM bila ini keliru.',
            self::Ended => 'Sesi Anda diakhiri oleh admin. Silakan login lagi.',
        };
    }
}
