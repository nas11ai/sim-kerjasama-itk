<?php

namespace Database\Seeders\Qa;

use App\Models\FormSubmission;
use Illuminate\Support\Carbon;

/**
 * Riwayat tahun lalu per set: empat pengajuan peneliti 01–04 di periode tahun lalu, lahir pada jamnya
 * sendiri (gerbang periode, Temporal Field Binding, dan tahun statistik membaca jam). Yang disetujui
 * dituntaskan sampai laporan Monev 1 dinilai dan luaran tercatat, supaya papan monev tidak berisi
 * siklus yatim bertenggat lewat. Dijalankan sebelum QaScenarios.
 */
final class QaHistory
{
    public const TITLES = [
        1 => 'Rancang Bangun Alat Pengering Hasil Panen Bertenaga Surya',
        2 => 'Penyuluhan Sanitasi Air Bersih di Desa Sepaku',
        3 => 'Pemanfaatan Citra Satelit untuk Pemantauan Tutupan Lahan IKN',
        4 => 'Pelatihan Pemasaran Daring bagi Perajin Manik Dayak',
    ];

    /** @return array<int, FormSubmission> kunci = nomor peneliti */
    public static function build(QaSet $set, QaSubmissionFlow $flow): array
    {
        $year = now()->year - 1;
        $at = fn (int $month, int $day, \Closure $work) => QaClock::at(Carbon::create($year, $month, $day, 10), $work);
        [$a, $b] = $set->reviewers;
        $op = $set->operator;
        $s = [];

        $at(2, 10, function () use ($set, $flow, &$s): void {
            foreach (self::TITLES as $n => $title) {
                $lead = $set->researchers[$n];
                $s[$n] = $flow->draft($lead, QaScenarios::schemeOf($n), "[{$set->tag()}] {$title}", 'last');
                $flow->team($s[$n], $lead, $set->member);
                $s[$n] = $flow->submit($s[$n], $lead);
            }
        });

        $at(4, 15, function () use ($flow, $a, $b, $op, &$s): void {
            foreach ($s as $n => $submission) {
                $flow->assign($submission, $a, $b);
                $flow->evaluate($submission, $a, $n === 4 ? 40 : 85);
                $flow->evaluate($submission, $b, $n === 4 ? 48 : 80);
                $flow->finish($submission, $a);
                $flow->finish($submission, $b);
            }
            foreach ([1, 2, 3] as $n) {
                $flow->approve($s[$n], $op);
            }
            $flow->reject($s[4], $op, 'Ruang lingkup kegiatan tidak sesuai dengan prioritas skema tahun ini.');
        });

        $reports = $at(8, 1, function () use ($flow, $set, $op, &$s): array {
            $reports = [];
            foreach ([1, 2, 3] as $n) {
                $reports[$n] = $flow->monev($s[$n], $op, $set->externalReviewer, send: true);
            }

            return $reports;
        });

        $at(8, 20, function () use ($flow, $set, $reports): void {
            foreach ($reports as $report) {
                $flow->evaluate($report, $set->externalReviewer, 84);
            }
        });

        $at(10, 1, function () use ($flow, $set, &$s): void {
            foreach ([1, 2, 3] as $n) {
                $flow->output($s[$n], $set->researchers[$n]);
            }
        });

        return array_map(fn (FormSubmission $submission): FormSubmission => $submission->refresh(), $s);
    }
}
