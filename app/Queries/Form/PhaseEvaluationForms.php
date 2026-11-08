<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\EvaluationSectionData;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Form evaluasi di editor tahap ADMIN-08-09: satu bagian per detail ber-`needs_review`, urut
 * `order`, aktif maupun nonaktif, tanpa kueri per form atau per field. W158/BR-FE-22: form baru
 * tak sampai ke reviewer yang sudah bertugas; `reviewsInProgress` menghitung pengajuannya.
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
                'reviewEvaluationForms' => fn (Relation $forms) => $forms
                    ->orderBy('order')
                    ->orderBy('id')
                    ->withCount('reviewerFormAssignments')
                    ->with(['reviewFormFields' => fn (Relation $fields) => $fields
                        ->orderBy('order')
                        ->orderBy('id')
                        ->withCount('reviewFormFieldResponses')
                        ->with(['reviewFormFieldOptions' => fn (Relation $options) => $options->orderBy('order')->orderBy('id')]),
                    ]),
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
