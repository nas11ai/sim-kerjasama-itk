<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Models\FormSubmission;
use App\Queries\Submission\SubmissionYear;
use Illuminate\Database\Eloquent\Builder;

/**
 * W149 — basis setiap angka statistik: pengajuan induk terkirim (tanpa Draft dan laporan
 * monev), sama dengan baris OperatorSubmissionList. WITHDRAWN tetap terhitung.
 */
final class ReportingSubmissions implements ChangesNoState
{
    /**
     * `withYear: false` untuk panel keadaan sekarang dan perbandingan antartahun.
     *
     * @return Builder<FormSubmission>
     */
    public static function base(StatisticsFilters $filters, bool $withYear = true): Builder
    {
        $query = FormSubmission::query()
            ->where('form_submissions.is_submitted', true)
            ->whereNull('form_submissions.parent_submission_id')
            ->when($filters->period !== null, fn (Builder $q) => $q->where('form_submissions.submission_period_id', $filters->period))
            ->when($filters->scheme !== null, fn (Builder $q) => $q->where('form_submissions.scheme_id', $filters->scheme));

        return $withYear ? SubmissionYear::apply($query, $filters->year) : $query;
    }
}
