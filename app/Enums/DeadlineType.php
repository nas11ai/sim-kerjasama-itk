<?php

namespace App\Enums;

/**
 * BR-FE-25 — jenis tenggat tetap, pengganti label teks bebas (W72). Tiap jenis punya satu
 * pemakai (BR-FE-26): `submit` pengisi, `evaluation` reviewer, `revision` pengisi saat revisi,
 * `output` penulis luaran di detail Upload Luaran (W139, tanpa siklus).
 */
enum DeadlineType: string
{
    case Submit = 'submit';
    case Evaluation = 'evaluation';
    case Revision = 'revision';
    case Output = 'output';

    /** BR-FE-07 — siklus terjauh sebuah langkah (Laporan Monev X); kalender periode ikut dibatasi. */
    public const MAX_CYCLE = 10;

    private const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

    /** W125 — nama siklus monev ke-n: romawi sampai X, angka sesudahnya. */
    public static function cycleName(int $cycle): string
    {
        return 'Monev '.(self::ROMAN[$cycle] ?? "{$cycle}");
    }

    /**
     * Label tampilan. Siklus kosong = pengajuan utama; siklus n = Monev n. Batas Submit
     * siklus monev dibaca "Batas Laporan Monev n" karena yang dikirim adalah laporan.
     */
    public function label(?int $cycle = null): string
    {
        if ($cycle === null) {
            return match ($this) {
                self::Submit => 'Batas Pengiriman',
                self::Evaluation => 'Batas Evaluasi',
                self::Revision => 'Batas Revisi',
                self::Output => 'Batas Akhir',
            };
        }

        $monev = self::cycleName($cycle);

        return match ($this) {
            self::Submit => "Batas Laporan {$monev}",
            self::Evaluation => "Batas Evaluasi {$monev}",
            self::Revision => "Batas Revisi {$monev}",
            self::Output => 'Batas Akhir',
        };
    }

    /**
     * Label jenis langkah fase (ADR 0019) — pilihan editor tahap: "Pengajuan", "Laporan Monev n",
     * "Upload Luaran". Hanya `submit` dan `output` yang menjadi jenis langkah.
     */
    public function stepLabel(?int $cycle = null): string
    {
        return match (true) {
            $this === self::Output => 'Unggah Luaran',
            $cycle === null => 'Pengajuan',
            default => 'Laporan '.self::cycleName($cycle),
        };
    }
}
