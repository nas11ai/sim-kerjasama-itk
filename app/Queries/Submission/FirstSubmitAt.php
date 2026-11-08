<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\AuditEntry;
use App\Models\FormSubmission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tanggal submit pertama sebagai subquery kolom: tabel pengajuan tidak menyimpannya,
 * jejak audit yang menyimpan (W7). Dipakai daftar peneliti dan daftar operator.
 */
final class FirstSubmitAt implements ChangesNoState
{
    /**
     * Laporan monev ditulis dengan deskripsi audit sendiri (`MonevCycles::REPORT_SUBMITTED_AUDIT`).
     *
     * @return Builder<AuditEntry>
     */
    public static function subquery(string $description = SubmitFormSubmission::AUDIT_DESCRIPTION): Builder
    {
        return self::entries($description)
            ->selectRaw('min(created_at)')
            ->whereColumn('subject_id', 'form_submissions.id');
    }

    /** W152 — umur sebagai hari penuh sejak submit pertama (statistik Saat Ini dan Tindak Lanjut). */
    public static function ageDays(string $firstSubmitAt): int
    {
        return (int) floor(CarbonImmutable::parse($firstSubmitAt)->diffInDays(now()));
    }

    /**
     * Entri submit seluruh pengajuan; juga dipakai SubmissionYear.
     *
     * @return Builder<AuditEntry>
     */
    public static function entries(string $description = SubmitFormSubmission::AUDIT_DESCRIPTION): Builder
    {
        return AuditEntry::query()->auditOnly()
            ->where('subject_type', FormSubmission::class)
            ->where('description', $description);
    }
}
