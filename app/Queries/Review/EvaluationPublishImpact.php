<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\EvaluationFormPin;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Dampak Terbitkan (§4.5): label versi yang akan lahir, dan berapa angkatan × langkah yang sudah
 * dipaku ke versi lama dan tetap memakainya (BR-REV-18). Pemakuan unik per angkatan × langkah ×
 * form, jadi satu baris pin = satu angkatan terdampak.
 *
 * @method static array{nextLabel: string, pinnedCohorts: int} run(ReviewEvaluationForm $form)
 */
class EvaluationPublishImpact implements ChangesNoState
{
    use AsAction;

    /** @return array{nextLabel: string, pinnedCohorts: int} */
    public function handle(ReviewEvaluationForm $form): array
    {
        $next = ($form->latestVersion()->first()->number ?? 0) + 1;

        return [
            'nextLabel' => ReviewEvaluationFormVersion::labelFor($next),
            'pinnedCohorts' => EvaluationFormPin::query()->where('review_evaluation_form_id', $form->id)->count(),
        ];
    }
}
