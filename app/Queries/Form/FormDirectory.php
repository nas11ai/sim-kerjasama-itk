<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormListInput;
use App\Models\Form;
use App\Queries\Concerns\NormalisesListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daftar formulir untuk layar Admin. Kolom urutan, arah, dan ukuran halaman datang dari
 * query string, jadi dibatasi whitelist: nilai tak dikenal jatuh ke bawaan, bukan 500.
 *
 * @phpstan-type Filters array{search: string|null, form_type: string|null, is_active: string|null, sort_by: string, sort_order: 'asc'|'desc', per_page: int}
 *
 * @method static array{forms: LengthAwarePaginator<int, Form>, filters: Filters} run(FormListInput $input)
 */
class FormDirectory implements ChangesNoState
{
    use AsAction;
    use NormalisesListFilters;

    private const SORTABLE = ['created_at', 'title', 'is_active', 'form_type'];

    /** @return array{forms: LengthAwarePaginator<int, Form>, filters: Filters} */
    public function handle(FormListInput $input): array
    {
        $filters = $this->filters($input);

        $forms = $this->sorted($this->query($filters), $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return ['forms' => $forms, 'filters' => $filters];
    }

    /** @return Filters */
    private function filters(FormListInput $input): array
    {
        return [
            'search' => $input->search === '' ? null : $input->search,
            'form_type' => $input->formType === '' ? null : $input->formType,
            'is_active' => $input->active === '' ? null : $input->active,
            'sort_by' => in_array($input->sortBy, self::SORTABLE, true) ? $input->sortBy : 'created_at',
            'sort_order' => $this->sortOrder($input->sortOrder),
            'per_page' => $this->perPage($input->perPage),
        ];
    }

    /**
     * @param  Filters  $filters
     * @return Builder<Form>
     */
    private function query(array $filters): Builder
    {
        $query = Form::query()->with(['formType', 'formFields']);
        $search = $filters['search'];

        if ($search !== null) {
            $query->where(fn (Builder $q) => $q
                ->whereLike('title', "%{$search}%")
                ->orWhereLike('description', "%{$search}%")
                ->orWhereHas('formType', fn (Builder $type) => $type->whereLike('name', "%{$search}%")));
        }

        // Selain angka (termasuk 'all') berarti tanpa filter; bigint vs teks galat di PostgreSQL.
        if ($filters['form_type'] !== null && ctype_digit($filters['form_type'])) {
            $query->where('form_type_id', $filters['form_type']);
        }

        $this->whereActiveState($query, $filters['is_active']);

        return $query;
    }

    /**
     * @param  Builder<Form>  $query
     * @param  Filters  $filters
     * @return Builder<Form>
     */
    private function sorted(Builder $query, array $filters): Builder
    {
        if ($filters['sort_by'] !== 'form_type') {
            return $query->orderBy("forms.{$filters['sort_by']}", $filters['sort_order']);
        }

        return $query->join('form_types', 'forms.form_type_id', '=', 'form_types.id')
            ->select('forms.*')
            ->orderBy('form_types.name', $filters['sort_order']);
    }
}
