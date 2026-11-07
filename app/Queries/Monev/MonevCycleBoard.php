<?php

namespace App\Queries\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Monev\MonevBoardRowData;
use App\Data\View\Monev\MonevCycleData;
use App\Enums\MonevBoardStatus;
use App\Enums\MonevCycleState;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Queries\Submission\OperatorSubmissionList;
use App\States\Submission\Approved;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-05-01 — induk APPROVED yang punya siklus monev (W125), dibaca pemegang
 * submissions.view-all (W129, BR-SM-07). Keadaan siklus dihitung hanya untuk baris di
 * halaman itu; karenanya tidak ada filter "Status Siklus" (W131).
 *
 * @phpstan-type Filters array{search: string, period: string, scheme: string}
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static array{rows: LengthAwarePaginator<int, MonevBoardRowData>, periodOptions: list<Option>, schemeOptions: list<Option>} run(Filters $filters)
 */
class MonevCycleBoard implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /**
     * @param  Filters  $filters
     * @return array{rows: LengthAwarePaginator<int, MonevBoardRowData>, periodOptions: list<Option>, schemeOptions: list<Option>}
     */
    public function handle(array $filters): array
    {
        $query = MonevCycles::whereHasCycles(FormSubmission::query())
            ->whereNull('parent_submission_id')
            ->whereState('status', Approved::class)
            ->whereHas('form', fn (Builder $form) => $form->where('form_type_id', '!=', FormType::BIODATA_ID))
            ->with(['form:id,title', 'scheme:id,name', 'submittedBy:id,name', 'formPhaseDetail']);

        $this->filter($query, $filters);

        return [
            'rows' => $query->latest('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (FormSubmission $parent): MonevBoardRowData => $this->row($parent)),
            ...OperatorSubmissionList::periodAndSchemeOptions(),
        ];
    }

    private function row(FormSubmission $parent): MonevBoardRowData
    {
        $current = collect(MonevCycles::run($parent))
            ->first(fn (MonevCycleData $cycle): bool => $cycle->state !== MonevCycleState::Done);

        return new MonevBoardRowData(
            id: $parent->id,
            title: $parent->form->title,
            leader: $parent->submittedBy->name,
            scheme: $parent->scheme?->name,
            currentCycle: $current?->name,
            status: MonevBoardStatus::of($current),
        );
    }

    /**
     * @param  Builder<FormSubmission>  $query
     * @param  Filters  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        $search = $filters['search'];

        // PostgreSQL — whereLike tidak peka huruf, judul atau nama ketua.
        $query->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $any) => $any
            ->whereHas('form', fn (Builder $form) => $form->whereLike('title', "%{$search}%"))
            ->orWhereHas('submittedBy', fn (Builder $lead) => $lead->whereLike('name', "%{$search}%"))));
        $query->when(ctype_digit($filters['period']), fn (Builder $q) => $q->where('submission_period_id', (int) $filters['period']));
        $query->when(ctype_digit($filters['scheme']), fn (Builder $q) => $q->where('scheme_id', (int) $filters['scheme']));
    }
}
