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
        // BR-REV-19 — relasi yang dimuat sebelum Apply bisa menunjuk jawaban yang sudah riwayat; jawaban
        // berlaku dibaca ulang di bawah kunci, jadi simpanan yang berpapasan dengan Apply menunggunya.
        $existing = $assignment->reviewFormResponse()->lockForUpdate()->first();

        if ($existing !== null) {
            $assignment->setRelation('reviewFormResponse', $existing);

            return $existing;
        }

        // BR-REV-18 — "mulai mengisi": jawaban pertama memaku versi bagi seluruh angkatan × langkah.
        $version = PinEvaluationVersion::run($assignment->submissionReviewer->formSubmission, $assignment->reviewEvaluationForm);
        // B10 — indeks unik jawaban berlaku: yang kalah dari "mulai mengisi" bersamaan membaca pemenangnya.
        $response = $assignment->reviewFormResponse()->createOrFirst([], [
            'status' => 'draft',
            'review_evaluation_form_version_id' => $version->id,
        ]);
        $assignment->setRelation('reviewFormResponse', $response);

        if (!$response->wasRecentlyCreated) {
            return $response;
        }

        $response->setRelation('reviewEvaluationFormVersion', $version);
        AuditTrail::record('Draft evaluasi dibuat', $response, $reviewer);

        return $response;
    }
}
