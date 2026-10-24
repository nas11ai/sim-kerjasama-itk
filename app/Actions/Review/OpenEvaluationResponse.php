<?php

namespace App\Actions\Review;

use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormResponse;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jawaban reviewer atas satu penugasan form evaluasi; draftnya dibuat saat pertama dibuka.
 *
 * @method static ReviewFormResponse run(ReviewerFormAssignment $assignment, User $reviewer)
 */
class OpenEvaluationResponse
{
    use AsAction;

    public function handle(ReviewerFormAssignment $assignment, User $reviewer): ReviewFormResponse
    {
        $existing = $assignment->reviewFormResponse;

        if ($existing !== null) {
            return $existing;
        }

        $response = $assignment->reviewFormResponse()->create(['status' => 'draft']);
        $assignment->setRelation('reviewFormResponse', $response);

        AuditTrail::record('Draft evaluasi dibuat', $response, $reviewer);

        return $response;
    }
}
