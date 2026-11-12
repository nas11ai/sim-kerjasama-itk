<?php

namespace App\Actions\Review;

use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormResponse;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jawaban reviewer atas satu penugasan form evaluasi; draftnya dibuat saat pertama disimpan, dan
 * saat itu juga versi formnya dipaku untuk angkatan × langkah pengajuannya (BR-REV-18).
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

        // BR-REV-18 — "mulai mengisi": jawaban pertama memaku versi bagi seluruh angkatan × langkah.
        $version = PinEvaluationVersion::run($assignment->submissionReviewer->formSubmission, $assignment->reviewEvaluationForm);
        $response = $assignment->reviewFormResponse()->create([
            'status' => 'draft',
            'review_evaluation_form_version_id' => $version->id,
        ]);
        $response->setRelation('reviewEvaluationFormVersion', $version);
        $assignment->setRelation('reviewFormResponse', $response);

        AuditTrail::record('Draft evaluasi dibuat', $response, $reviewer);

        return $response;
    }
}
