<?php

namespace App\Support\Reporting\Exports;

use App\Data\Reporting\ExportFilters;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\SubmissionYear;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Database\Eloquent\Builder;

/**
 * W154 — pengajuan yang diekspor: induk terkirim (basis W149) dalam batas baca peminta
 * (BR-RPT-10), lalu filter. Filter hanya menyempitkan; batasnya tidak pernah melebar.
 */
final class ExportedSubmissions
{
    /** Kunci filter keempat jenis berbasis pengajuan. */
    public const FILTER_KEYS = [ExportFilters::YEAR, ExportFilters::SCHEME, ExportFilters::STATUS];

    /** @return Builder<FormSubmission> */
    public static function for(User $user, ExportFilters $filters): Builder
    {
        $query = SubmissionVisibility::for($user)
            ->where('form_submissions.is_submitted', true)
            ->whereNull('form_submissions.parent_submission_id')
            ->when($filters->scheme !== null, fn (Builder $q) => $q->where('form_submissions.scheme_id', $filters->scheme))
            ->when($filters->status !== null, fn (Builder $q) => $q->where('form_submissions.status', $filters->status));

        return SubmissionYear::between($query, $filters->yearFrom, $filters->yearTo);
    }

    /** @return Builder<FormSubmission> subquery id, untuk tabel anak (anggaran, luaran) */
    public static function idsFor(User $user, ExportFilters $filters): Builder
    {
        return self::for($user, $filters)->select('form_submissions.id');
    }
}
