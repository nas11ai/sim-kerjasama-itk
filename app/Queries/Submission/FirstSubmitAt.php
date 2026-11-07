<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\AuditEntry;
use App\Models\FormSubmission;
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
        return AuditEntry::query()->auditOnly()
            ->selectRaw('min(created_at)')
            ->where('subject_type', FormSubmission::class)
            ->whereColumn('subject_id', 'form_submissions.id')
            ->where('description', $description);
    }
}
