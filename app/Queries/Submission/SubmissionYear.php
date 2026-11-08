<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\AuditEntry;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * W149 — tahun pengajuan = tahun submit pertama (W7), satu definisi untuk statistik
 * dan daftar Operator supaya angka dan daftar drill-down tidak berselisih.
 */
final class SubmissionYear implements ChangesNoState
{
    /** @return Builder<AuditEntry> */
    public static function subquery(): Builder
    {
        return FirstSubmitAt::subquery()->select(new Expression('EXTRACT(YEAR FROM min(created_at))::int'));
    }

    /**
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public static function apply(Builder $query, ?int $year): Builder
    {
        return $query->when($year !== null, fn (Builder $q) => $q->whereIn(
            'form_submissions.id',
            FirstSubmitAt::entries()
                ->select('subject_id')
                ->groupBy('subject_id')
                ->havingRaw('EXTRACT(YEAR FROM min(created_at)) = ?', [$year]),
        ));
    }

    /**
     * Tahun-tahun yang punya pengajuan induk terkirim, terbaru dulu (pilihan filter).
     *
     * @return list<int>
     */
    public static function years(): array
    {
        $years = FormSubmission::query()
            ->where('is_submitted', true)
            ->whereNull('parent_submission_id')
            ->select(['submit_year' => self::subquery()]);

        $found = DB::query()->fromSub($years, 'years')
            ->whereNotNull('submit_year')
            ->distinct()
            ->orderByDesc('submit_year')
            ->pluck('submit_year');

        // Kolom ber-cast ::int, jadi pdo_pgsql mengembalikan int.
        return array_values(array_filter($found->all(), is_int(...)));
    }
}
