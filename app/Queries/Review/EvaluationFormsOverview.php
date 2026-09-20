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
 * Form evaluasi yang berlaku bagi sebuah pengajuan, beserta progres penugasan
 * yang masih berlaku. Dua kueri untuk seluruh form; versi lama menjalankan dua
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

        $assigned = self::countPerForm(self::applicableAssignments($submission));
        $completed = self::countPerForm(
            self::applicableAssignments($submission)->whereRelation('reviewFormResponse', 'status', 'submitted'),
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
     * Penugasan yang masih berlaku saja: milik reviewer aktif (BR-REV-05) dan
     * belum dicabut. Nama tabel disebut eksplisit karena HasManyThrough
     * menggabung dua tabel yang sama-sama punya `status` dan `is_active`.
     *
     * @return HasManyThrough<ReviewerFormAssignment, SubmissionReviewer, FormSubmission>
     */
    private static function applicableAssignments(FormSubmission $submission): HasManyThrough
    {
        return $submission->reviewerFormAssignments()
            ->where('reviewer_form_assignments.is_active', true)
            ->where('submission_reviewers.status', 'active');
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
