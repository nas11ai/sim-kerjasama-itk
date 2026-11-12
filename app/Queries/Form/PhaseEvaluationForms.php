<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\EvaluationSectionData;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Editor tahap ADMIN-08-09: satu bagian per detail ber-`needs_review` berisi pemasangannya
 * (BR-FE-30), tanpa kueri per pemasangan. `reviewsInProgress` untuk banner W158.
 *
 * @method static list<EvaluationSectionData> run(FormPhase $phase)
 */
class PhaseEvaluationForms implements ChangesNoState
{
    use AsAction;

    /** @return list<EvaluationSectionData> */
    public function handle(FormPhase $phase): array
    {
        $details = $phase->formPhaseDetails()
            ->where('needs_review', true)
            ->with([
                'phaseType:id,name',
                'formAccessControl.form:id,title',
                'stepEvaluationForms' => fn (Relation $steps) => $steps
                    ->select('step_evaluation_forms.*')
                    // BR-FE-22 — penugasan di langkah ini saja; > 0 berarti tak bisa dilepas.
                    ->addSelect(['assignment_count' => ReviewerFormAssignment::query()->selectRaw('count(*)')
                        ->join('submission_reviewers', 'submission_reviewers.id', '=', 'reviewer_form_assignments.submission_reviewer_id')
                        ->join('form_submissions', 'form_submissions.id', '=', 'submission_reviewers.form_submission_id')
                        ->whereColumn('reviewer_form_assignments.review_evaluation_form_id', 'step_evaluation_forms.review_evaluation_form_id')
                        ->whereColumn('form_submissions.form_phase_detail_id', 'step_evaluation_forms.form_phase_detail_id'),
                    ])
                    ->orderBy('order')
                    ->orderBy('id')
                    ->with('reviewEvaluationForm.latestVersion'),
            ])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $inProgress = $this->reviewsInProgress($details);

        return array_values($details
            ->map(fn (FormPhaseDetail $detail): EvaluationSectionData => EvaluationSectionData::fromModel(
                $detail,
                $inProgress->get($detail->id, 0),
            ))
            ->all());
    }

    /**
     * Pengajuan per detail yang punya reviewer aktif dan belum diputus. Satu kueri untuk semua detail.
     *
     * @param  EloquentCollection<int, FormPhaseDetail>  $details
     * @return Collection<array-key, int>
     */
    private function reviewsInProgress(EloquentCollection $details): Collection
    {
        return FormSubmission::query()
            ->whereIn('form_phase_detail_id', $details->modelKeys())
            ->whereState('status', [Submitted::class, UnderReview::class, NeedsRevision::class, Resubmitted::class])
            ->whereHas('activeSubmissionReviewers')
            ->pluck('form_phase_detail_id')
            ->countBy();
    }
}
