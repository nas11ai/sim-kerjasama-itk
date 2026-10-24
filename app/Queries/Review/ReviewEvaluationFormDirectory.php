<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daftar form evaluasi untuk layar Admin beserta pilihan filternya. Filter tahap dan detail
 * yang bukan angka diabaikan oleh FormRequest-nya; bigint vs teks galat di PostgreSQL.
 *
 * @method static array{evaluationForms: LengthAwarePaginator<int, ReviewEvaluationForm>, formPhases: Collection<int, FormPhase>, formPhaseDetails: Collection<int, FormPhaseDetail>} run(?int $formPhaseId, ?int $formPhaseDetailId, ?string $search)
 */
class ReviewEvaluationFormDirectory implements ChangesNoState
{
    use AsAction;

    /** @return array{evaluationForms: LengthAwarePaginator<int, ReviewEvaluationForm>, formPhases: Collection<int, FormPhase>, formPhaseDetails: Collection<int, FormPhaseDetail>} */
    public function handle(?int $formPhaseId, ?int $formPhaseDetailId, ?string $search): array
    {
        $query = ReviewEvaluationForm::query()->with([
            'formPhaseDetail.formPhase:id,title',
            'formPhaseDetail.formAccessControl.form:id,title',
            'reviewFormFields',
        ]);

        if ($formPhaseId !== null) {
            $query->whereHas('formPhaseDetail', fn (Builder $detail) => $detail->where('form_phase_id', $formPhaseId));
        }

        if ($formPhaseDetailId !== null) {
            $query->where('form_phase_detail_id', $formPhaseDetailId);
        }

        if ($search !== null) {
            $query->where(fn (Builder $q) => $q
                ->whereLike('title', "%{$search}%")
                ->orWhereLike('description', "%{$search}%"));
        }

        return [
            'evaluationForms' => $query->orderBy('order')->paginate(15)->withQueryString(),
            'formPhases' => FormPhase::query()->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'formPhaseDetails' => FormPhaseDetail::query()
                ->with(['formPhase:id,title', 'formAccessControl.form:id,title'])
                ->whereHas('formPhase', fn (Builder $phase) => $phase->where('is_active', true))
                ->get(),
        ];
    }
}
