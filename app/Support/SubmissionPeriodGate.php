<?php

namespace App\Support;

use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;

/**
 * BR-FE-01, BR-SCH-07, BR-SM-13 — kapan sebuah SubmissionPeriod masih menerima kerja. Dua
 * pertanyaan berbeda: menerima pengajuan **baru** (Batas Submit siklus utama, K8d) dan masih
 * menampung kerja apa pun (seluruh tanggal, untuk arsip draft dan Close Early).
 */
final class SubmissionPeriodGate
{
    /**
     * BR-FE-01 — pengajuan baru hanya lahir selama Batas Submit siklus utama belum lewat.
     * Tanggal lain di depan (evaluasi, revisi, monev) milik tahap sesudah submit.
     */
    public static function isOpen(?SubmissionPeriod $period): bool
    {
        if ($period === null) {
            return true;
        }

        // BR-SCH-07 — penutupan paksa Operator mengalahkan tanggal apa pun.
        if ($period->is_force_closed) {
            return false;
        }

        $submit = self::submitDeadline($period);

        return $submit !== null && !$submit->datetime->isPast();
    }

    /**
     * BR-FE-25 — Batas Submit siklus pengajuan utama; paling banyak satu per periode.
     */
    public static function submitDeadline(SubmissionPeriod $period): ?SubmissionDate
    {
        return $period->submissionDates->first(
            static fn (SubmissionDate $date): bool => $date->type === DeadlineType::Submit && $date->cycle === null,
        );
    }

    /**
     * BR-SM-13 — periode tak lagi menampung kerja apa pun: ditutup paksa, atau seluruh
     * tanggalnya lewat. Sengaja bukan Batas Submit: draft revisi tidak boleh terarsip sebelum
     * Batas Revisi.
     */
    public static function isFullyClosed(?SubmissionPeriod $period): bool
    {
        if ($period === null) {
            return false;
        }

        if ($period->is_force_closed) {
            return true;
        }

        $dates = $period->submissionDates;

        // `every()` atas koleksi kosong bernilai true secara hampa: periode yang baru
        // dibuat tanpa tanggal bukan periode yang sudah lewat.
        if ($dates->isEmpty()) {
            return false;
        }

        // Seluruh `SubmissionDate` adalah **batas akhir**, bukan jendela mulai–selesai.
        return $dates->every(
            static fn (SubmissionDate $date): bool => $date->datetime->isPast()
        );
    }

    /**
     * Status untuk ditampilkan, diturunkan dari predikat yang sama dengan gerbang pengajuan
     * baru (K8d) — "Dibuka" berarti pengajuan baru diterima.
     *
     * @return 'force_closed'|'no_dates'|'expired'|'active'
     */
    public static function displayStatus(SubmissionPeriod $period): string
    {
        if ($period->is_force_closed) {
            return 'force_closed';
        }

        if (self::submitDeadline($period) === null) {
            return 'no_dates';
        }

        // `upcoming` sengaja tidak ada: setiap tanggal batas akhir, bukan tanggal mulai.
        return self::isOpen($period) ? 'active' : 'expired';
    }

    /**
     * BR-SCH-07 — apakah periode di balik satu FormPhaseDetail ditutup paksa.
     * Periodenya dibaca lewat SubmissionDate milik detail itu, bukan formPhase:
     * `form_phases` tidak punya kolom `submission_period_id` (ADR 0014).
     */
    public static function isForceClosed(FormPhaseDetail $detail): bool
    {
        // BR-SCH-07 (v2.5) — Close Early menutup tahap pengajuan; siklus monev mengikuti BR-MON-05
        // dan Upload Luaran BR-RO-08 (W139), walau Batas Akhir juga tanpa siklus.
        return $detail->submissionDate->cycle === null
            && $detail->submissionDate->type !== DeadlineType::Output
            && $detail->submissionDate->submissionPeriod?->is_force_closed === true;
    }

    public static function assertOpen(?SubmissionPeriod $period): void
    {
        if (self::isOpen($period)) {
            return;
        }

        abort(403, $period !== null && $period->is_force_closed
            ? 'Periode pengajuan sudah ditutup Operator. Pengajuan baru tidak dapat dibuat.'
            : 'Periode pengajuan sudah berakhir. Pengajuan baru tidak dapat dibuat.');
    }
}
