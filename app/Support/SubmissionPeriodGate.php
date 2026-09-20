<?php

namespace App\Support;

use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use Illuminate\Support\Carbon;

/**
 * BR-FE-01 dan BR-SCH-07 — kapan sebuah SubmissionPeriod masih menerima kerja.
 * Predikat yang sama dipakai `FormSubmission::resolveIsArchived()` (BR-SM-13);
 * ia memanggil ke sini alih-alih menyalinnya.
 */
final class SubmissionPeriodGate
{
    /**
     * BR-FE-01 — pengajuan baru hanya lahir selama periodenya masih terbuka.
     */
    public static function isOpen(?SubmissionPeriod $period): bool
    {
        return !self::isClosed($period);
    }

    public static function isClosed(?SubmissionPeriod $period): bool
    {
        if ($period === null) {
            return false;
        }

        // BR-SCH-07 — penutupan paksa Operator mengalahkan tanggal apa pun.
        if ($period->is_force_closed) {
            return true;
        }

        $dates = $period->submissionDates;

        // Periode yang belum punya tanggal sama sekali bukan periode yang sudah
        // lewat — `every()` atas koleksi kosong bernilai true secara hampa, dan
        // tanpa penjagaan ini periode yang baru dibuat langsung terbaca tertutup.
        if ($dates->isEmpty()) {
            return false;
        }

        // Seluruh `SubmissionDate` adalah **batas akhir**, bukan jendela
        // mulai–selesai. Predikat warisan `isPeriodActive()` membacanya
        // sebagai jendela, jadi melarang mengajukan sebelum batasnya tiba.
        return $dates->every(
            static fn (SubmissionDate $date): bool => $date->datetime->isPast()
        );
    }

    /**
     * Status untuk ditampilkan, diturunkan dari predikat yang sama dengan
     * gerbangnya — dihitung terpisah, periode yang ditutup paksa pernah
     * berlabel "Aktif" sementara backend menolak setiap pengajuan ke sana.
     *
     * @return 'force_closed'|'no_dates'|'expired'|'active'
     */
    public static function displayStatus(SubmissionPeriod $period): string
    {
        if ($period->is_force_closed) {
            return 'force_closed';
        }

        if ($period->submissionDates->isEmpty()) {
            return 'no_dates';
        }

        // `upcoming` sengaja tidak ada: ia lahir dari pembacaan bahwa tanggal
        // paling awal adalah tanggal mulai, padahal semuanya batas akhir —
        // periode sudah menerima pengajuan sejak dibuat.
        return self::isClosed($period) ? 'expired' : 'active';
    }

    /**
     * Sisa hari sampai batas terakhir. `null` kalau periodenya belum punya
     * tanggal; `0` kalau seluruhnya sudah lewat.
     */
    public static function daysRemaining(SubmissionPeriod $period): ?int
    {
        $last = $period->submissionDates
            ->sortBy(static fn (SubmissionDate $date): string => $date->datetime->toDateTimeString())
            ->last();

        if (!$last instanceof SubmissionDate) {
            return null;
        }

        $now = Carbon::now();

        return $now->gt($last->datetime) ? 0 : (int) $now->diffInDays($last->datetime, false);
    }

    /**
     * BR-SCH-07 — apakah periode di balik satu FormPhaseDetail ditutup paksa.
     * Periodenya dibaca lewat SubmissionDate milik detail itu, bukan formPhase:
     * `form_phases` tidak punya kolom `submission_period_id` (ADR 0014).
     */
    public static function isForceClosed(FormPhaseDetail $detail): bool
    {
        return $detail->submissionDate->submissionPeriod?->is_force_closed === true;
    }

    public static function assertOpen(?SubmissionPeriod $period): void
    {
        if (!self::isClosed($period)) {
            return;
        }

        abort(403, $period !== null && $period->is_force_closed
            ? 'Periode pengajuan sudah ditutup Operator. Pengajuan baru tidak dapat dibuat.'
            : 'Periode pengajuan sudah berakhir. Pengajuan baru tidak dapat dibuat.');
    }
}
