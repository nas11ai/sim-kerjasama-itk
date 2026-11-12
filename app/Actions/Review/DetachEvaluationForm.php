<?php

namespace App\Actions\Review;

use App\Actions\Form\AssertCurrentPhaseStep;
use App\Actions\Form\PublishPhaseVersion;
use App\Data\Request\PhaseStructureInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Models\StepEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Melepas Form Evaluasi dari satu langkah. BR-FE-30, BR-FE-32 (G3-W5) — pelepasan berlaku mulai
 * versi struktur berikutnya; pemasangan dan penugasan di versi lama tetap utuh (BR-FE-22).
 *
 * @method static void run(StepEvaluationForm $step, User $actor)
 */
class DetachEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(StepEvaluationForm $step, User $actor): void
    {
        $detail = $step->formPhaseDetail;
        $structure = PhaseStructureInput::fromVersion(AssertCurrentPhaseStep::run($detail));
        $index = $structure->indexOf($detail->id);
        $kept = array_values(array_filter(
            $structure->details[$index]->evaluationForms,
            fn (StepEvaluationFormInput $a): bool => $a->reviewEvaluationFormId !== $step->review_evaluation_form_id,
        ));

        DB::transaction(function () use ($step, $actor, $detail, $structure, $index, $kept): void {
            PublishPhaseVersion::run($detail->formPhase ?? $detail->formPhase()->firstOrFail(), $structure->withEvaluationFormsAt($index, $kept), $actor);

            AuditTrail::record('Form evaluasi dilepas', $step, $actor, new AuditChange(old: [
                'form_phase_detail_id' => $step->form_phase_detail_id,
                'review_evaluation_form_id' => $step->review_evaluation_form_id,
            ]));
        });
    }
}
