<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pengajuan terkirim satu periode di layar Operator, tersaring fase, state, dan pengaju.
 *
 * @phpstan-type Filters array{form_phase_id: ?string, status: ?string, search: ?string}
 *
 * @method static array{formPhases: Collection<int, FormPhase>, submissions: LengthAwarePaginator<int, FormSubmission>} run(SubmissionPeriod $period, Filters $filters)
 */
class OperatorPeriodSubmissions implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /**
     * @param  Filters  $filters
     * @return array{formPhases: Collection<int, FormPhase>, submissions: LengthAwarePaginator<int, FormSubmission>}
     */
    public function handle(SubmissionPeriod $period, array $filters): array
    {
        $query = FormSubmission::query()
            ->with(['form.formType', 'submittedBy:id,name,email'])
            ->where('is_submitted', true)
            ->where('submission_period_id', $period->id);

        $this->filter($query, $filters);

        return [
            'formPhases' => FormPhase::query()
                ->whereHas('submissionPeriodPhases', fn (Builder $q) => $q->where('submission_period_id', $period->id))
                ->with('formPhaseDetails.formAccessControl.form.formType')
                ->get(),
            'submissions' => $query->orderBy('created_at', 'desc')->paginate(self::PER_PAGE)->withQueryString(),
        ];
    }

    /**
     * @param  Builder<FormSubmission>  $query
     * @param  Filters  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        $phase = $filters['form_phase_id'];
        $status = $filters['status'];
        $search = $filters['search'];

        $query->when($phase !== null && $phase !== '', fn (Builder $q) => $q->whereHas(
            'formPhaseDetail',
            fn (Builder $detail) => $detail->where('form_phase_id', $phase),
        ));
        $query->when($status !== null && $status !== '', fn (Builder $q) => $q->where('status', $status));
        $query->when($search !== null && $search !== '', fn (Builder $q) => $q->whereHas(
            'submittedBy',
            fn (Builder $user) => $user->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"),
        ));
    }
}
