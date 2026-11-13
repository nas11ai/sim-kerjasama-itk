<?php

namespace Database\Seeders\Qa;

use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\Reviewer;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Volume institusi: 30 peneliti latar di seluruh prodi, masing-masing satu pengajuan tahun lalu dan satu
 * tahun ini. Gunanya hanya layar tingkat institusi (paginasi, grafik per fakultas, perbandingan tahun);
 * judulnya berawalan `[Latar]` dan tidak dipakai matriks QA.
 */
final class QaBackground
{
    public const RESEARCHERS = 30;

    private const TOPICS = [
        'Material Komposit Serat Alam', 'Jaringan Sensor Nirkabel', 'Pengolahan Limbah Cair Industri',
        'Ketahanan Struktur Jembatan', 'Kualitas Udara Perkotaan', 'Sistem Informasi Desa',
        'Budidaya Rumput Laut', 'Efisiensi Pembangkit Listrik', 'Transportasi Publik Terintegrasi',
        'Konservasi Mangrove', 'Keamanan Siber UMKM', 'Pangan Lokal Bergizi',
    ];

    private const PLACES = ['Balikpapan', 'Samarinda', 'Penajam Paser Utara', 'Kutai Kartanegara', 'Paser', 'Bontang'];

    public static function build(QaSubmissionFlow $flow): void
    {
        $prodi = Organization::where('type', 'study_program')->whereNotNull('parent_id')->orderBy('id')->get()->all();
        $operator = QaClock::at(Carbon::create(now()->year - 1, 1, 6, 8), fn () => QaAccounts::operator('latar-operator', 'Latar Operator LPPM'));
        [$a, $b] = QaClock::at(Carbon::create(now()->year - 1, 1, 6, 8), fn (): array => [
            QaAccounts::reviewer('latar-penilai1', 'Latar Penilai Satu', 'internal'),
            QaAccounts::reviewer('latar-penilai2', 'Latar Penilai Dua', 'internal'),
        ]);

        $researchers = QaClock::at(Carbon::create(now()->year - 1, 1, 7, 9), function () use ($prodi): array {
            $users = [];
            foreach (range(1, self::RESEARCHERS) as $n) {
                $users[$n] = QaAccounts::researcher(
                    sprintf('latar-peneliti%02d', $n),
                    'Latar '.fake()->firstName().' '.fake()->lastName(),
                    $prodi[($n - 1) % count($prodi)],
                    sprintf('%010d', 7_200_000_000 + $n),
                );
            }

            return $users;
        });

        self::lastYear($flow, $researchers, $operator, $a, $b);
        self::thisYear($flow, $researchers, $operator, $a, $b);
    }

    /**
     * 20 Disetujui (monev dinilai, luaran tercatat) dan 10 Ditolak, semuanya pada jam tahun lalu.
     *
     * @param  array<int, User>  $researchers
     */
    private static function lastYear(QaSubmissionFlow $flow, array $researchers, User $operator, Reviewer $a, Reviewer $b): void
    {
        $year = now()->year - 1;
        $at = fn (int $month, int $day, \Closure $work) => QaClock::at(Carbon::create($year, $month, $day, 11), $work);
        $s = [];

        $at(3, 1, function () use ($flow, $researchers, &$s): void {
            foreach ($researchers as $n => $lead) {
                $s[$n] = $flow->submit($flow->draft($lead, QaScenarios::schemeOf($n), self::title($n), 'last'), $lead);
            }
        });

        $at(4, 20, function () use ($flow, $operator, $a, $b, &$s): void {
            foreach ($s as $n => $submission) {
                self::review($flow, $submission, $a, $b, $n % 3 === 0 ? 45 : 82);
                $n % 3 === 0
                    ? $flow->reject($submission, $operator, 'Belum memenuhi kriteria kelayakan skema.')
                    : $flow->approve($submission, $operator);
            }
        });

        $approved = array_filter($s, fn (int $n): bool => $n % 3 !== 0, ARRAY_FILTER_USE_KEY);

        $reports = $at(8, 5, fn (): array => array_map(fn (FormSubmission $p): FormSubmission => $flow->monev($p, $operator, $b, send: true), $approved));
        $at(8, 25, function () use ($flow, $b, $reports): void {
            foreach ($reports as $report) {
                $flow->evaluate($report, $b, 80);
            }
        });
        $at(10, 10, function () use ($flow, $researchers, $approved): void {
            foreach ($approved as $n => $submission) {
                $flow->output($submission, $researchers[$n]);
            }
        });
    }

    /**
     * 10 Menunggu Penilaian, 10 Sedang Dinilai (5 di antaranya sudah satu penilaian), 6 Disetujui, 4 Ditolak.
     *
     * @param  array<int, User>  $researchers
     */
    private static function thisYear(QaSubmissionFlow $flow, array $researchers, User $operator, Reviewer $a, Reviewer $b): void
    {
        $today = now()->toImmutable();
        $at = fn (int $daysAgo, \Closure $work) => QaClock::at($today->subDays($daysAgo)->setTime(11, 0), $work);
        $s = [];

        $at(30, function () use ($flow, $researchers, &$s): void {
            foreach ($researchers as $n => $lead) {
                $s[$n] = $flow->submit($flow->draft($lead, QaScenarios::schemeOf($n + 1), self::title($n + 40)), $lead);
            }
        });

        $at(20, function () use ($flow, $a, $b, &$s): void {
            foreach (range(11, 30) as $n) {
                $flow->assign($s[$n], $a, $b);
            }
            foreach (range(16, 20) as $n) {
                $flow->evaluate($s[$n], $a);
            }
        });

        $at(12, function () use ($flow, $operator, $a, $b, &$s): void {
            foreach (range(21, 30) as $n) {
                self::evaluateAndFinish($flow, $s[$n], $a, $b, $n > 26 ? 45 : 82);
                $n > 26
                    ? $flow->reject($s[$n], $operator, 'Rencana kerja belum terukur.')
                    : $flow->approve($s[$n], $operator);
            }
        });
    }

    /** Penugasan dua penilai, penilaian, dan Selesai pada satu jam yang sama. */
    private static function review(QaSubmissionFlow $flow, FormSubmission $submission, Reviewer $a, Reviewer $b, int $score): void
    {
        $flow->assign($submission, $a, $b);
        self::evaluateAndFinish($flow, $submission, $a, $b, $score);
    }

    private static function evaluateAndFinish(QaSubmissionFlow $flow, FormSubmission $submission, Reviewer $a, Reviewer $b, int $score): void
    {
        $flow->evaluate($submission, $a, $score);
        $flow->evaluate($submission, $b, $score);
        $flow->finish($submission, $a);
        $flow->finish($submission, $b);
    }

    private static function title(int $n): string
    {
        $verbs = ['Kajian', 'Pengembangan', 'Analisis', 'Penerapan', 'Pendampingan'];

        return '[Latar] '.$verbs[$n % count($verbs)].' '.self::TOPICS[$n % count(self::TOPICS)].' di '.self::PLACES[$n % count(self::PLACES)];
    }
}
