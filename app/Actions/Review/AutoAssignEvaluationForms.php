<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Queries\Submission\EvaluationDueDate;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menugaskan seluruh form evaluasi wajib ke reviewer yang belum punya penugasan.
 * Versi lama memuat kueri form wajib di dalam perulangan reviewer; ia kini
 * diambil sekali karena isinya sama untuk setiap reviewer.
 *
 * @method static int run(FormSubmission $submission)
 */
class AutoAssignEvaluationForms
{
    use AsAction;

    /** @return int jumlah penugasan yang dibuat */
    public function handle(FormSubmission $submission): int
    {
        $detail = $submission->formPhaseDetail;

        if ($detail === null || !$detail->hasReviewEvaluationForms()) {
            return 0;
        }

        $requiredForms = $detail->requiredReviewEvaluationForms()->get();
        $reviewers = $submission->submissionReviewers()->whereDoesntHave('reviewerFormAssignments')->get();

        if ($requiredForms->isEmpty() || $reviewers->isEmpty()) {
            return 0;
        }

        $dueDate = EvaluationDueDate::run($submission);

        foreach ($reviewers as $reviewer) {
            foreach ($requiredForms as $form) {
                $reviewer->assignForm($form->id, true, $dueDate);
            }
        }

        $created = $reviewers->count() * $requiredForms->count();

        AuditTrail::record('Form evaluasi wajib ditugaskan otomatis', $submission, null, null, ['assignments' => $created]);

        return $created;
    }
}
