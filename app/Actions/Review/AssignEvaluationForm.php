<?php

namespace App\Actions\Review;

use App\Models\ReviewerFormAssignment;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use App\Support\Review\EvaluationStatusRecalculator;
use DateTimeInterface;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menugaskan satu form evaluasi kepada seorang reviewer.
 *
 * @method static ReviewerFormAssignment run(SubmissionReviewer $reviewer, int $formId, bool $isRequired = true, ?DateTimeInterface $dueDate = null)
 */
class AssignEvaluationForm
{
    use AsAction;

    public function handle(
        SubmissionReviewer $reviewer,
        int $formId,
        bool $isRequired = true,
        ?DateTimeInterface $dueDate = null,
    ): ReviewerFormAssignment {
        $assignment = $reviewer->reviewerFormAssignments()->create([
            'review_evaluation_form_id' => $formId,
            'is_required' => $isRequired,
            'due_date' => $dueDate,
            'assigned_at' => now(),
        ]);

        // BR-REV-05 — pekerjaannya bertambah, jadi statusnya ikut dihitung ulang.
        EvaluationStatusRecalculator::for($reviewer);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Form evaluasi ditugaskan', $assignment);

        return $assignment;
    }
}
