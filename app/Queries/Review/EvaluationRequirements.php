<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Syarat evaluasi sebuah pengajuan, untuk layar diskusi review.
 *
 * @method static array{required: bool, has_forms: bool, message: string, total_forms?: int, required_forms?: int} run(FormSubmission $submission)
 */
class EvaluationRequirements implements ChangesNoState
{
    use AsAction;

    /** @return array{required: bool, has_forms: bool, message: string, total_forms?: int, required_forms?: int} */
    public function handle(FormSubmission $submission): array
    {
        $detail = $submission->formPhaseDetail;

        if ($detail === null || !$detail->hasReviewEvaluationForms()) {
            return [
                'required' => false,
                'has_forms' => false,
                'message' => 'No evaluation forms required for this submission.',
            ];
        }

        return [
            'required' => true,
            'has_forms' => true,
            'total_forms' => $detail->review_evaluation_forms_count,
            'required_forms' => $detail->required_review_evaluation_forms_count,
            'message' => 'This submission requires evaluation forms to be completed before creating review threads.',
        ];
    }
}
