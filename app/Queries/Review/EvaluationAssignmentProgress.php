<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewerFormAssignment;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Kemajuan satu penugasan form evaluasi untuk dibaca lewat AJAX.
 *
 * @method static array<string, bool|int|string|Carbon|null> run(ReviewerFormAssignment $assignment)
 */
class EvaluationAssignmentProgress implements ChangesNoState
{
    use AsAction;

    /** @return array<string, bool|int|string|Carbon|null> */
    public function handle(ReviewerFormAssignment $assignment): array
    {
        $deadline = [
            'is_overdue' => $assignment->isOverdue(),
            'days_until_due' => $assignment->getDaysUntilDue(),
        ];
        $response = $assignment->reviewFormResponse;

        if ($response === null) {
            return ['status' => 'not_started', 'completion_percentage' => 0, 'can_submit' => false, ...$deadline];
        }

        return [
            'status' => $response->status,
            'completion_percentage' => EvaluationFormCompletion::run($response),
            'can_submit' => $response->canBeSubmitted(),
            'can_edit' => $response->canBeEdited(),
            ...$deadline,
            'submitted_at' => $response->submitted_at,
        ];
    }
}
