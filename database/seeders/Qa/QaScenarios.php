<?php

namespace Database\Seeders\Qa;

use App\Models\FormSubmission;

/**
 * Dua belas pengajuan tahun ini per set, satu per baris tabel desain. Peneliti `nn` memiliki skenario
 * `nn`: BR-SM-16 (satu pengajuan aktif per periode+skema) dan "satu draf terbaru per form+periode"
 * tidak saling bentur. Peristiwanya disebar mundur beberapa minggu supaya linimasa terasa wajar.
 */
final class QaScenarios
{
    public const TITLES = [
        1 => 'Pemetaan Potensi Energi Surya di Kawasan Pesisir Balikpapan',
        2 => 'Pelatihan Pengolahan Sampah Plastik untuk UMKM Kelurahan Manggar',
        3 => 'Optimasi Campuran Beton Geopolimer Berbasis Abu Terbang Batu Bara',
        4 => 'Pendampingan Literasi Digital bagi Guru Sekolah Dasar di Penajam',
        5 => 'Model Prediksi Banjir Rob Berbasis Pembelajaran Mesin',
        6 => 'Penerapan Hidroponik Sederhana untuk Ketahanan Pangan Keluarga',
        7 => 'Karakterisasi Biochar dari Limbah Cangkang Sawit',
        8 => 'Edukasi Mitigasi Bencana Tanah Longsor bagi Warga Batu Ampar',
        9 => 'Sistem Pemantauan Kualitas Air Sungai Ampal Berbasis IoT',
        10 => 'Penguatan Kapasitas Kelompok Nelayan dalam Pengolahan Hasil Tangkap',
        11 => 'Analisis Kestabilan Lereng Jalan Tol Balikpapan Samarinda',
        12 => 'Pemberdayaan Bank Sampah Berbasis Aplikasi di Kelurahan Graha Indah',
    ];

    /** @return array<int, FormSubmission> kunci = nomor baris tabel desain */
    public static function build(QaSet $set, QaSubmissionFlow $flow): array
    {
        $at = fn (int $daysAgo, \Closure $work) => QaClock::at(QaClock::ago($daysAgo), $work);
        [$a, $b] = $set->reviewers;
        $op = $set->operator;
        $lead = fn (int $n) => $set->researchers[$n];
        $s = [];

        // Peneliti mengisi dan mengirim.
        $at(35, function () use ($set, $flow, $lead, &$s): void {
            foreach (range(3, 12) as $n) {
                $s[$n] = $flow->draft($lead($n), self::schemeOf($n), "[{$set->tag()}] ".self::TITLES[$n]);
                if ($n >= 9) {
                    $flow->team($s[$n], $lead($n), $set->member);
                }
                $s[$n] = $flow->submit($s[$n], $lead($n));
            }
        });

        // Operator menugaskan; #4 sengaja baru satu dari dua penilai (status tetap Menunggu Penilaian).
        $at(28, function () use ($flow, $a, $b, &$s): void {
            $flow->assign($s[4], $a);
            foreach (range(5, 12) as $n) {
                $flow->assign($s[$n], $a, $b);
            }
        });

        // Penilai mengisi lembar penilaian. #7 dan #8: penilai A membuka catatan sebelum Selesai.
        $at(21, function () use ($flow, $a, $b, &$s): void {
            $flow->evaluate($s[5], $a);
            foreach (range(6, 12) as $n) {
                $flow->evaluate($s[$n], $a, $n === 11 ? 45 : 82);
                $flow->evaluate($s[$n], $b, $n === 11 ? 50 : 78);
            }
            foreach ([7, 8] as $n) {
                $flow->openThread($s[$n], $a, 'Perjelas metode pengambilan sampel dan jadwal kegiatan bulan ketiga.');
            }
        });

        // Kedua penilai Selesai; Operator memutuskan yang siap.
        $at(14, function () use ($flow, $a, $b, $op, &$s): void {
            foreach (range(6, 12) as $n) {
                $flow->finish($s[$n], $a);
                $flow->finish($s[$n], $b);
            }
            foreach ([9, 10, 12] as $n) {
                $flow->approve($s[$n], $op);
            }
            $flow->reject($s[11], $op, 'Kebaruan penelitian belum tampak dan rencana anggaran tidak sesuai ruang lingkup skema.');
        });

        // #8: peneliti merevisi dan mengirim ulang; putaran pemeriksaan dibuka untuk penilai A.
        $at(10, fn () => $flow->revise($s[8], $lead(8)));

        // Monev dan penarikan.
        $at(7, function () use ($flow, $set, $op, &$s): void {
            $flow->monev($s[9], $op, $set->externalReviewer, send: false);
            $flow->monev($s[10], $op, $set->externalReviewer, send: true);
            $flow->withdraw($s[12], $op, 'Ketua pengusul mengundurkan diri karena tugas belajar ke luar negeri.');
        });

        $at(3, fn () => $flow->output($s[9], $lead(9)));

        // Draf terbaru.
        $at(2, function () use ($set, $flow, $lead, &$s): void {
            $s[1] = $flow->draft($lead(1), self::schemeOf(1), "[{$set->tag()}] ".self::TITLES[1], untilStep: 2);
            $s[2] = $flow->draft($lead(2), self::schemeOf(2), "[{$set->tag()}] ".self::TITLES[2]);
        });

        ksort($s);

        return array_map(fn (FormSubmission $submission): FormSubmission => $submission->refresh(), $s);
    }

    /** Ganjil = penelitian (bergantian Dasar/Terapan), genap = pengabdian. */
    public static function schemeOf(int $n): string
    {
        if ($n % 2 === 0) {
            return 'pengabdian';
        }

        return $n % 4 === 1 ? 'dasar' : 'terapan';
    }
}
