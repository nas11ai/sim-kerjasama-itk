<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\FormRowData;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Support\Search\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ADMIN-08-05 — seluruh Form, tersaring di server; nilai filter tak dikenal diabaikan.
 *
 * @phpstan-type Filters array{search: string, type: string, status: string}
 * @phpstan-type Option array{value: string, label: string}
 * @phpstan-type Result array{forms: LengthAwarePaginator<int, FormRowData>, typeOptions: list<Option>, statusOptions: list<Option>}
 *
 * @method static Result run(Filters $filters)
 */
class FormDirectory implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /**
     * @param  Filters  $filters
     * @return Result
     */
    public function handle(array $filters): array
    {
        $query = Form::query()
            ->with('formType')
            // W81 — dua hitungan pengajuan: aktif (tanpa REJECTED/WITHDRAWN) dan semuanya (BR-FE-19).
            ->withCount([
                'formFields as field_count',
                'formSubmissions as active_submission_count' => $this->activeSubmissions(...),
                'formSubmissions as submission_count',
            ])
            ->latest('id');

        $this->filter($query, $filters);

        return [
            'forms' => $query->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (Form $form): FormRowData => FormRowData::fromModel($form)),
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => [
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
        ];
    }

    /** @param  Builder<FormSubmission>  $query */
    private function activeSubmissions(Builder $query): void
    {
        $query->active();
    }

    /**
     * @param  Builder<Form>  $query
     * @param  Filters  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        $query->when($filters['search'] !== '', fn (Builder $q) => $q->whereLike('title', LikePattern::contains($filters['search'])));
        // Selain angka (termasuk 'all') berarti tanpa filter; bigint vs teks galat di PostgreSQL.
        $query->when(ctype_digit($filters['type']), fn (Builder $q) => $q->where('form_type_id', $filters['type']));

        match ($filters['status']) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
    }

    /**
     * W80 — semua tipe ditawarkan sebagai filter, Biodata sekalipun.
     *
     * @return list<Option>
     */
    private function typeOptions(): array
    {
        return array_values(FormType::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (FormType $type): array => ['value' => (string) $type->id, 'label' => $type->name])
            ->all());
    }
}
