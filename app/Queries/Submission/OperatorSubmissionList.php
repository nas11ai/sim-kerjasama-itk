<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Manage\OperatorSubmissionRowData;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Queries\Review\DecisionReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-02-01 — seluruh pengajuan induk yang sudah terkirim (BR-SM-07, view-all).
 * Laporan monev (anak) milik layar M1, jadi tidak terdaftar di sini.
 *
 * @phpstan-type Filters array{search: string, status: string, period: string, scheme: string, year: string, decision: string}
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static array{submissions: LengthAwarePaginator<int, OperatorSubmissionRowData>, statusOptions: list<Option>, periodOptions: list<Option>, schemeOptions: list<Option>, yearOptions: list<Option>} run(Filters $filters)
 */
class OperatorSubmissionList implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /**
     * @param  Filters  $filters
     * @return array{submissions: LengthAwarePaginator<int, OperatorSubmissionRowData>, statusOptions: list<Option>, periodOptions: list<Option>, schemeOptions: list<Option>, yearOptions: list<Option>}
     */
    public function handle(array $filters): array
    {
        $statusOptions = SubmissionStatusChoices::submitted();
        $query = FormSubmission::query()
            ->where('is_submitted', true)
            ->whereNull('parent_submission_id')
            ->with(['form:id,title', 'scheme:id,name', 'submittedBy:id,name', 'submittedBy.organization:organizations.id,name'])
            ->withCount(['submissionReviewers as active_reviewers_count' => fn (Builder $q) => $q->where('status', 'active')])
            ->addSelect(['form_submissions.*', 'submitted_at' => FirstSubmitAt::subquery()]);

        $this->filter($query, $filters, array_column($statusOptions, 'value'));

        return [
            'submissions' => $query->orderByDesc('updated_at')
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (FormSubmission $s): OperatorSubmissionRowData => OperatorSubmissionRowData::fromModel($s)),
            'statusOptions' => $statusOptions,
            ...self::periodAndSchemeOptions(),
            'yearOptions' => array_map(fn (int $y): array => ['value' => (string) $y, 'label' => (string) $y], SubmissionYear::years()),
        ];
    }

    /**
     * Juga dipakai papan monev Operator (OPERATOR-05-01), supaya kedua filter bersumber sama.
     *
     * @return array{periodOptions: list<Option>, schemeOptions: list<Option>}
     */
    public static function periodAndSchemeOptions(): array
    {
        return [
            'periodOptions' => self::options(SubmissionPeriod::query()->latest('id')),
            'schemeOptions' => self::options(Scheme::query()->orderBy('name')),
        ];
    }

    /**
     * @param  Builder<FormSubmission>  $query
     * @param  Filters  $filters
     * @param  list<string>  $statuses
     */
    private function filter(Builder $query, array $filters, array $statuses): void
    {
        $search = $filters['search'];

        // PostgreSQL — whereLike tidak peka huruf, judul atau nama ketua.
        $query->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $any) => $any
            ->whereHas('form', fn (Builder $form) => $form->whereLike('title', "%{$search}%"))
            ->orWhereHas('submittedBy', fn (Builder $lead) => $lead->whereLike('name', "%{$search}%"))));
        // Nilai di luar pilihan diabaikan, bukan menghasilkan daftar kosong yang membingungkan.
        $query->when(in_array($filters['status'], $statuses, true), fn (Builder $q) => $q->where('status', $filters['status']));
        $query->when(ctype_digit($filters['period']), fn (Builder $q) => $q->where('submission_period_id', (int) $filters['period']));
        $query->when(ctype_digit($filters['scheme']), fn (Builder $q) => $q->where('scheme_id', (int) $filters['scheme']));
        // W149 — tahun submit pertama, definisi yang sama dengan statistik (drill-down).
        $query->when(ctype_digit($filters['year']), fn (Builder $q) => SubmissionYear::apply($q, (int) $filters['year']));
        // BR-REV-05 — "Menunggu keputusan": definisi SQL yang sama dengan kartu dashboard.
        $query->when($filters['decision'] === 'awaiting', fn (Builder $q) => DecisionReadiness::whereReady($q));
    }

    /**
     * @param  Builder<SubmissionPeriod>|Builder<Scheme>  $query
     * @return list<Option>
     */
    private static function options(Builder $query): array
    {
        return array_values($query->get(['id', 'name'])
            ->map(fn (SubmissionPeriod|Scheme $m): array => ['value' => (string) $m->id, 'label' => $m->name])
            ->all());
    }
}
