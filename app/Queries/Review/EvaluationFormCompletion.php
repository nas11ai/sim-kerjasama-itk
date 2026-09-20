<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewFormResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Persentase field yang sudah terisi pada satu jawaban evaluasi. Form tanpa
 * field dihitung 100% — bukan 0% — supaya ia tidak menahan apa pun.
 *
 * @method static int run(ReviewFormResponse $response)
 */
class EvaluationFormCompletion implements ChangesNoState
{
    use AsAction;

    public function handle(ReviewFormResponse $response): int
    {
        $totalFields = $response->reviewerFormAssignment
            ->reviewEvaluationForm
            ->reviewFormFields()
            ->count();

        if ($totalFields === 0) {
            return 100;
        }

        $filled = $response->reviewFormFieldResponses()
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->count();

        return min(100, (int) round($filled / $totalFields * 100));
    }
}
