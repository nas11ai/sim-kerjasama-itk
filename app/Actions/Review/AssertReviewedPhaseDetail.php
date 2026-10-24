<?php

namespace App\Actions\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhaseDetail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menurut ubiquitous language, ReviewEvaluationForm dipasang ke FormPhaseDetail yang
 * `needs_review = true`. Di detail lain tidak ada reviewer yang ditugaskan untuk mengisinya.
 *
 * @method static void run(int $formPhaseDetailId)
 */
class AssertReviewedPhaseDetail implements ChangesNoState
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(int $formPhaseDetailId): void
    {
        if (FormPhaseDetail::query()->whereKey($formPhaseDetailId)->where('needs_review', true)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'form_phase_detail_id' => 'Detail tahap ini tidak direview, jadi tidak bisa diberi form evaluasi. Pilih detail yang direview.',
        ]);
    }
}
