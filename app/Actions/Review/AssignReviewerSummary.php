<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\ReviewSummary;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Membuka ReviewSummary untuk seorang reviewer pada sebuah pengajuan.
 * Idempoten: reviewer yang sudah punya ulir tidak mendapat ulir kedua, dan
 * catatannya tidak ditimpa. summary_notes wajib — kolomnya NOT NULL.
 *
 * @method static ReviewSummary run(FormSubmission $submission, int $reviewerId, string $notes)
 */
class AssignReviewerSummary
{
    use AsAction;

    public function handle(FormSubmission $submission, int $reviewerId, string $notes): ReviewSummary
    {
        $summary = $submission->reviewSummaries()->firstOrCreate(
            ['reviewer_id' => $reviewerId],
            ['status' => 'open', 'summary_notes' => $notes],
        );

        if (!$summary->wasRecentlyCreated) {
            return $summary;
        }

        // BR-RPT-09 — hanya ulir yang benar-benar baru yang dicatat.
        AuditTrail::record('Ulir review dibuka untuk reviewer', $summary);

        return $summary;
    }
}
