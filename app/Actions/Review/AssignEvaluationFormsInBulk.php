<?php

namespace App\Actions\Review;

use App\Data\Request\FormAssignmentInput;
use App\Data\Review\BulkFormAssignmentResult;
use App\Models\FormSubmission;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menugaskan beberapa form evaluasi kepada beberapa reviewer pada satu pengajuan.
 *
 * @method static BulkFormAssignmentResult run(FormSubmission $submission, list<int> $reviewerIds, list<FormAssignmentInput> $forms)
 */
class AssignEvaluationFormsInBulk
{
    use AsAction;

    /**
     * @param  list<int>  $reviewerIds
     * @param  list<FormAssignmentInput>  $forms
     */
    public function handle(FormSubmission $submission, array $reviewerIds, array $forms): BulkFormAssignmentResult
    {
        $assignedCount = 0;
        $skipped = [];

        DB::transaction(function () use ($submission, $reviewerIds, $forms, &$assignedCount, &$skipped): void {
            foreach ($reviewerIds as $reviewerId) {
                // BR-SM-07, BR-REV-10 — hanya reviewer yang masih bertugas; yang lain dilewati,
                // bukan menggagalkan seluruh permintaan.
                $submissionReviewer = ActiveReviewerAssignment::run($submission, $reviewerId);

                if ($submissionReviewer === null) {
                    $skipped[] = $reviewerId;

                    continue;
                }

                foreach ($forms as $form) {
                    // BR-RPT-09 — AssignEvaluationForm menulis jejak tiap penugasan.
                    AssignEvaluationForm::run($submissionReviewer, $form->formId, $form->isRequired, $form->dueDate);
                }

                $assignedCount++;
            }
        });

        // BR-RPT-09 — satu permintaan, satu entri audit (di samping entri tiap penugasan).
        AuditTrail::record('Penugasan form evaluasi massal diproses', $submission);

        return new BulkFormAssignmentResult($assignedCount, $skipped);
    }
}
