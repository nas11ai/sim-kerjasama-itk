<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\EvaluationFormPin;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-19, BR-FE-17 — pengajuan angkatan × langkah pin ini yang reviewer-nya belum mengirim versi
 * terbaru padahal Batas Evaluasinya lewat tanpa perpanjangan: mengisi ulang terkunci sampai Operator
 * memberi override (K-3 b — Apply tidak menggeser tenggat).
 *
 * @method static Collection<int, FormSubmission> run(EvaluationFormPin $pin)
 */
class SubmissionsPastEvaluationDeadline implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, FormSubmission> */
    public function handle(EvaluationFormPin $pin): Collection
    {
        $latest = $pin->reviewEvaluationForm->latestVersion()->value('id');

        return FormSubmission::query()
            ->where('submission_period_id', $pin->submission_period_id)
            ->where('form_phase_detail_id', $pin->form_phase_detail_id)
            ->whereHas('submissionReviewers', fn (Builder $seat) => $seat
                ->where('status', 'active')
                ->whereHas('reviewerFormAssignments', fn (Builder $assignment) => $assignment
                    ->where('review_evaluation_form_id', $pin->review_evaluation_form_id)
                    ->where('due_date', '<', now())
                    ->whereDoesntHave('reviewFormResponse', fn (Builder $response) => $response
                        ->where('status', 'submitted')
                        ->where('review_evaluation_form_version_id', $latest))))
            // Padanan SQL PhaseAccessGate::activeOverride.
            ->whereNotIn('id', FormSubmissionOverride::query()
                ->select('form_submission_id')
                ->where('form_phase_detail_id', $pin->form_phase_detail_id)
                ->where('is_active', true)
                ->where(fn (Builder $open) => $open->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->with('submittedBy:id,name')
            ->orderBy('id')
            ->get();
    }
}
