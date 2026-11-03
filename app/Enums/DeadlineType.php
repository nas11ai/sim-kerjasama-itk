<?php

namespace App\Enums;

/**
 * BR-FE-25 — jenis tenggat tetap, pengganti label teks bebas (W72). Tiap jenis punya satu
 * pemakai (BR-FE-26): `submit` pengisi, `evaluation` reviewer, `revision` pengisi saat revisi.
 */
enum DeadlineType: string
{
    case Submit = 'submit';
    case Evaluation = 'evaluation';
    case Revision = 'revision';

    private const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

    /**
     * Label tampilan. Siklus kosong = pengajuan utama; siklus n = Monev n. Batas Submit
     * siklus monev dibaca "Batas Laporan Monev n" karena yang dikirim adalah laporan.
     */
    public function label(?int $cycle = null): string
    {
        if ($cycle === null) {
            return match ($this) {
                self::Submit => 'Batas Submit',
                self::Evaluation => 'Batas Evaluasi',
                self::Revision => 'Batas Revisi',
            };
        }

        $monev = 'Monev '.(self::ROMAN[$cycle] ?? "{$cycle}");

        return match ($this) {
            self::Submit => "Batas Laporan {$monev}",
            self::Evaluation => "Batas Evaluasi {$monev}",
            self::Revision => "Batas Revisi {$monev}",
        };
    }
}
