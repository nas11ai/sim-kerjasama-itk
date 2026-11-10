<?php

namespace App\Enums;

/**
 * BR-REV-16 — jenis putaran review. Penilaian dibuka saat reviewer ditugaskan; pemeriksaan
 * dibuka saat peneliti mengirim ulang revisi.
 */
enum ReviewRoundKind: string
{
    case Assessment = 'assessment';
    case Recheck = 'recheck';

    /** Label tombol tanda Selesai bagi peserta putaran ini. */
    public function finishLabel(): string
    {
        return match ($this) {
            self::Assessment => 'Selesai menilai',
            self::Recheck => 'Selesai memeriksa',
        };
    }
}
