<?php

namespace App\Queries\Scheme;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Scheme\SchemeRowData;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionType;
use App\Support\Search\ActiveStatusFilter;
use App\Support\Search\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ADMIN-08-03 — seluruh skema, tersaring di server (W111); nilai filter tak dikenal diabaikan.
 *
 * @phpstan-type Filters array{search: string, type: string, status: string}
 * @phpstan-type Option array{value: string, label: string}
 * @phpstan-type Result array{schemes: LengthAwarePaginator<int, SchemeRowData>, typeOptions: list<Option>, statusOptions: list<Option>}
 *
 * @method static Result run(Filters $filters)
 */
class SchemeDirectory implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /**
     * @param  Filters  $filters
     * @return Result
     */
    public function handle(array $filters): array
    {
        $query = Scheme::query()
            ->with(['schemeType:id,name', 'submissionType:id,name'])
            // BR-SCH-01 — angka yang sama dengan penjaga DeactivateScheme.
            ->withCount(['formSubmissions as active_submissions_count' => $this->activeSubmissions(...)])
            ->orderBy('name');

        $this->filter($query, $filters);

        return [
            'schemes' => $query->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (Scheme $scheme): SchemeRowData => SchemeRowData::fromModel($scheme)),
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => ActiveStatusFilter::OPTIONS,
        ];
    }

    /**
     * @param  Builder<Scheme>  $query
     * @param  Filters  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        $pattern = LikePattern::contains($filters['search']);
        $query->when($filters['search'] !== '', fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner->whereLike('name', $pattern)->orWhereLike('code', $pattern),
        ));
        $typeId = ActiveStatusFilter::id($filters['type']);
        $query->when($typeId !== null, fn (Builder $q) => $q->where('submission_type_id', $typeId));
        ActiveStatusFilter::apply($query, $filters['status']);
    }

    /** @param  Builder<FormSubmission>  $query */
    private function activeSubmissions(Builder $query): void
    {
        $query->active();
    }

    /**
     * W111 — kolom "Tipe" frame adalah Tipe Submission.
     *
     * @return list<Option>
     */
    private function typeOptions(): array
    {
        return array_values(SubmissionType::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (SubmissionType $type): array => ['value' => (string) $type->id, 'label' => $type->name])
            ->all());
    }
}
