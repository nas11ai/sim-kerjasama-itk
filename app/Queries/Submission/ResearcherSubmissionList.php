<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\SubmissionRowData;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * RESEARCHER-02-01 — pengajuan yang diketuai atau diikuti peneliti (BR-SM-07), tersaring
 * judul, status, dan periode. `view-all` sengaja tidak melebarkannya: ini "pengajuan saya".
 *
 * @phpstan-type Filters array{search: string, status: string, period: string}
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static array{submissions: LengthAwarePaginator<int, SubmissionRowData>, statusOptions: list<Option>, periodOptions: list<Option>} run(User $viewer, Filters $filters)
 */
class ResearcherSubmissionList implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /**
     * @param  Filters  $filters
     * @return array{submissions: LengthAwarePaginator<int, SubmissionRowData>, statusOptions: list<Option>, periodOptions: list<Option>}
     */
    public function handle(User $viewer, array $filters): array
    {
        $statusOptions = SubmissionStatusChoices::run();
        $query = SubmissionVisibility::ownedOrJoinedBy($viewer)
            ->with(['form:id,title', 'scheme:id,name', 'submissionPeriod'])
            ->addSelect(['form_submissions.*', 'submitted_at' => FirstSubmitAt::subquery()]);

        $this->filter($query, $filters, array_column($statusOptions, 'value'));

        return [
            'submissions' => $query->orderByDesc('updated_at')
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (FormSubmission $s): SubmissionRowData => SubmissionRowData::fromModel($s, $viewer)),
            'statusOptions' => $statusOptions,
            'periodOptions' => $this->periodOptions($viewer),
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
        $status = $filters['status'];
        $period = $filters['period'];

        $query->when($search !== '', fn (Builder $q) => $q->whereHas(
            'form',
            fn (Builder $form) => $form->whereLike('title', "%{$search}%"),
        ));
        // Nilai di luar pilihan diabaikan, bukan menghasilkan daftar kosong yang membingungkan.
        $query->when(in_array($status, $statuses, true), fn (Builder $q) => $q->where('status', $status));
        $query->when(ctype_digit($period), fn (Builder $q) => $q->where('submission_period_id', (int) $period));
    }

    /** @return list<Option> */
    private function periodOptions(User $viewer): array
    {
        return array_values(SubmissionPeriod::query()
            ->whereIn('id', SubmissionVisibility::ownedOrJoinedBy($viewer)->select('submission_period_id'))
            ->latest('id')
            ->get(['id', 'name'])
            ->map(fn (SubmissionPeriod $p): array => ['value' => (string) $p->id, 'label' => $p->name])
            ->all());
    }
}
