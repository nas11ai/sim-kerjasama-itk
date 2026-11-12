<?php

namespace App\Actions\Review;

use App\Actions\Form\AssertCurrentPhaseStep;
use App\Actions\Form\PublishPhaseVersion;
use App\Data\Request\PhaseStructureInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-30 — memasang Form Evaluasi pustaka di satu langkah `needs_review`, paling akhir di antara
 * pemasangan langkah itu. BR-FE-32 (G3-W4, sementara sampai draf tahap) — pemasangan menerbitkan
 * versi struktur berikutnya; aturan terbit-saja dan sekali-per-langkah ditegakkan PublishPhaseVersion.
 *
 * @method static StepEvaluationForm run(FormPhaseDetail $detail, ReviewEvaluationForm $form, bool $isRequired, User $actor)
 */
class AttachEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhaseDetail $detail, ReviewEvaluationForm $form, bool $isRequired, User $actor): StepEvaluationForm
    {
        AssertReviewedPhaseDetail::run($detail->id);
        $structure = PhaseStructureInput::fromVersion(AssertCurrentPhaseStep::run($detail));
        $index = $structure->indexOf($detail->id);
        $attached = [...$structure->details[$index]->evaluationForms, new StepEvaluationFormInput($form->id, $isRequired, true)];

        return DB::transaction(function () use ($detail, $form, $isRequired, $actor, $structure, $index, $attached): StepEvaluationForm {
            $version = PublishPhaseVersion::run($detail->formPhase ?? $detail->formPhase()->firstOrFail(), $structure->withEvaluationFormsAt($index, $attached), $actor);
            $step = $version->formPhaseDetails()->offset($index)->firstOrFail()->stepEvaluationForms()
                ->where('review_evaluation_form_id', $form->id)->firstOrFail();

            AuditTrail::record('Form evaluasi dipasang', $step, $actor, new AuditChange(new: [
                'form_phase_detail_id' => $step->form_phase_detail_id,
                'review_evaluation_form_id' => $form->id,
                'is_required' => $isRequired,
            ]));

            return $step;
        });
    }
}
