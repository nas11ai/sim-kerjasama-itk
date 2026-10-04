<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormAccessListInput;
use App\Models\FormAccessControl;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daftar kontrol akses untuk layar Admin, dikelompokkan per form. Filter menentukan form
 * mana yang tampil; setiap kelompok tetap memuat semua kontrol akses form itu.
 *
 * @phpstan-type Filters array{form_id: string|null, permission: string|null, faculty_id: string|null, study_program_id: string|null, search: string|null}
 *
 * @method static array{groups: LengthAwarePaginator<int, FormAccessControl>, filters: Filters} run(FormAccessListInput $input)
 */
class FormAccessControlDirectory implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 10;

    /** @return array{groups: LengthAwarePaginator<int, FormAccessControl>, filters: Filters} */
    public function handle(FormAccessListInput $input): array
    {
        $groups = $this->filtered($input)
            ->select('form_id')
            ->selectRaw('COUNT(*) as jumlah_access_controls')
            ->groupBy('form_id')
            ->with('form')
            ->orderByRaw('MAX(created_at) DESC')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $controls = FormAccessControl::query()
            ->with(['studyProgram.faculty'])
            ->whereIn('form_id', $groups->getCollection()->pluck('form_id'))
            ->get()
            ->groupBy('form_id');

        $groups->getCollection()->each(fn (FormAccessControl $group) => $group->setAttribute(
            'controls',
            $controls->get($group->form_id, collect()),
        ));

        return ['groups' => $groups, 'filters' => $this->filters($input)];
    }

    /** @return Builder<FormAccessControl> */
    private function filtered(FormAccessListInput $input): Builder
    {
        $query = FormAccessControl::query();

        if ($input->formId !== '') {
            $query->where('form_id', $this->id($input->formId));
        }

        if ($input->permission !== '') {
            $query->where('permission', $input->permission);
        }

        // ADR 0013 — fakultas berarti unit itu sendiri dan seluruh keturunannya, termasuk
        // prodi di bawah departemen; dulu hanya anak langsung yang terlihat.
        if ($input->facultyId !== '') {
            $query->whereIn('organization_id', Organization::subtreeIds($this->id($input->facultyId)));
        }

        if ($input->organizationId !== '') {
            $query->where('organization_id', $this->id($input->organizationId));
        }

        if ($input->search !== '') {
            $this->search($query, '%'.$input->search.'%');
        }

        return $query;
    }

    /** @param  Builder<FormAccessControl>  $query */
    private function search(Builder $query, string $pattern): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereHas('form', fn (Builder $form) => $form->whereLike('title', $pattern))
            ->orWhereLike('permission', $pattern)
            ->orWhereHas('organization', fn (Builder $organization) => $organization->whereLike('name', $pattern)));
    }

    /** Id dari query string; yang bukan angka tidak cocok dengan baris mana pun. */
    private function id(string $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) ?: 0;
    }

    /** @return Filters */
    private function filters(FormAccessListInput $input): array
    {
        $nullable = fn (string $value): ?string => $value === '' ? null : $value;

        return [
            'form_id' => $nullable($input->formId),
            'permission' => $nullable($input->permission),
            'faculty_id' => $nullable($input->facultyId),
            'study_program_id' => $nullable($input->organizationId),
            'search' => $nullable($input->search),
        ];
    }
}
