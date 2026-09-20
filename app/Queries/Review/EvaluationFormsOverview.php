<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Form evaluasi yang berlaku bagi sebuah pengajuan, beserta progres penugasannya.
 * Penugasan dihitung dua kueri untuk seluruh form; versi lama menjalankan dua
 * COUNT per form di dalam perulangan.
 *
 * @method static array<int, array{form: ReviewEvaluationForm, assigned_count: int, completed_count: int, completion_percentage: int}> run(FormSubmission $submission)
 */
class EvaluationFormsOverview implements ChangesNoState
{
    use AsAction;

    /** @return array<int, array{form: ReviewEvaluationForm, assigned_count: int, completed_count: int, completion_percentage: int}> */
    public function handle(FormSubmission $submission): array
    {
        $detail = $submission->formPhaseDetail;

        if ($detail === null) {
            return [];
        }

        $assigned = self::countPerForm($submission->reviewerFormAssignments());
        $completed = self::countPerForm(
            $submission->reviewerFormAssignments()->whereRelation('reviewFormResponse', 'status', 'submitted'),
        );

        $overview = [];

        foreach ($detail->activeReviewEvaluationForms()->get() as $form) {
            $assignedCount = $assigned[$form->id] ?? 0;
            $completedCount = $completed[$form->id] ?? 0;

            $overview[] = [
                'form' => $form,
                'assigned_count' => $assignedCount,
                'completed_count' => $completedCount,
                'completion_percentage' => $assignedCount > 0 ? (int) round($completedCount / $assignedCount * 100) : 0,
            ];
        }

        return $overview;
    }

    /**
     * @param  HasManyThrough<ReviewerFormAssignment, SubmissionReviewer, FormSubmission>  $assignments
     * @return array<int> form id => jumlah penugasan
     */
    private static function countPerForm(HasManyThrough $assignments): array
    {
        return $assignments->get(['review_evaluation_form_id'])->countBy('review_evaluation_form_id')->all();
    }
}
