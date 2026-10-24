<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewEvaluationForm;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Berapa kali form evaluasi ditugaskan dan berapa yang sudah dijawab. "Sudah dijawab" dibaca
 * dari `submitted_at`, bukan status `submitted`: jawaban yang lalu dikunci berstatus `locked`
 * dan dulu terhitung belum selesai.
 *
 * @method static array{total_assignments: int, completed_responses: int, pending_responses: int} run(ReviewEvaluationForm $form)
 */
class EvaluationFormUsage implements ChangesNoState
{
    use AsAction;

    /** @return array{total_assignments: int, completed_responses: int, pending_responses: int} */
    public function handle(ReviewEvaluationForm $form): array
    {
        $total = $form->reviewerFormAssignments()->count();
        $completed = $form->reviewerFormAssignments()
            ->whereHas('reviewFormResponse', fn (Builder $response) => $response->whereNotNull('submitted_at'))
            ->count();

        return [
            'total_assignments' => $total,
            'completed_responses' => $completed,
            'pending_responses' => $total - $completed,
        ];
    }
}
