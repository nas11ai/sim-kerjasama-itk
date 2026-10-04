<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseListInput;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daftar tahap formulir untuk layar Admin. Kolom urutan, arah, dan ukuran halaman datang
 * dari query string, jadi dibatasi whitelist: nilai tak dikenal jatuh ke bawaan, bukan 500.
 *
 * @phpstan-type Filters array{search: string|null, is_active: string|null, sort_by: string, sort_order: 'asc'|'desc', per_page: int}
 *
 * @method static array{phases: LengthAwarePaginator<int, FormPhase>, filters: Filters} run(FormPhaseListInput $input)
 */
class FormPhaseDirectory implements ChangesNoState
{
    use AsAction;

    private const SORTABLE = ['created_at', 'title', 'is_active'];

    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 100;

    /** @return array{phases: LengthAwarePaginator<int, FormPhase>, filters: Filters} */
    public function handle(FormPhaseListInput $input): array
    {
        $filters = $this->filters($input);

        $phases = $this->query($filters)
            ->orderBy($filters['sort_by'], $filters['sort_order'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        $phases->getCollection()->each(function (FormPhase $phase) {
            $phase->review_evaluation_forms_count = $phase->formPhaseDetails->sum(
                fn (FormPhaseDetail $detail) => $detail->reviewEvaluationFormsCount
            );
            $phase->required_review_evaluation_forms_count = $phase->formPhaseDetails->sum(
                fn (FormPhaseDetail $detail) => $detail->requiredReviewEvaluationFormsCount
            );
        });

        return ['phases' => $phases, 'filters' => $filters];
    }

    /** @return Filters */
    private function filters(FormPhaseListInput $input): array
    {
        return [
            'search' => $input->search === '' ? null : $input->search,
            'is_active' => $input->active === '' ? null : $input->active,
            'sort_by' => in_array($input->sortBy, self::SORTABLE, true) ? $input->sortBy : 'created_at',
            'sort_order' => $input->sortOrder === 'asc' ? 'asc' : 'desc',
            'per_page' => $input->perPage < 1
                ? self::DEFAULT_PER_PAGE
                : min($input->perPage, self::MAX_PER_PAGE),
        ];
    }

    /**
     * @param  Filters  $filters
     * @return Builder<FormPhase>
     */
    private function query(array $filters): Builder
    {
        $query = FormPhase::query()->with([
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType',
            'formPhaseDetails.reviewEvaluationForms',
        ]);

        if ($filters['search'] !== null) {
            $query->where(fn ($q) => $q
                ->whereLike('title', '%'.$filters['search'].'%')
                ->orWhereLike('description', '%'.$filters['search'].'%'));
        }

        // Kolom is_active bertipe boolean di PostgreSQL; bandingkan dengan boolean.
        if ($filters['is_active'] === 'active' || $filters['is_active'] === 'inactive') {
            $query->where('is_active', $filters['is_active'] === 'active');
        }

        return $query;
    }
}
