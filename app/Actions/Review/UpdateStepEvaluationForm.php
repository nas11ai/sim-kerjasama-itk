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
 * Wajib dan aktif sebuah pemasangan (G2-W2). Menonaktifkan berhenti menawarkan form ini untuk
 * pekerjaan baru (BR-FE-22). BR-FE-32 (G3-W4, sementara) — perubahan menerbitkan versi struktur
 * berikutnya; penugasan dan angkatan di versi lama tidak tersentuh.
 *
 * @method static void run(StepEvaluationForm $step, bool $isRequired, bool $isActive, User $actor)
 */
class UpdateStepEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(StepEvaluationForm $step, bool $isRequired, bool $isActive, User $actor): void
    {
        if ($step->is_required === $isRequired && $step->is_active === $isActive) {
            return;
        }

        $detail = $step->formPhaseDetail;
        $structure = PhaseStructureInput::fromVersion(AssertCurrentPhaseStep::run($detail));
        $index = $structure->indexOf($detail->id);
        $attached = array_map(
            fn (StepEvaluationFormInput $a): StepEvaluationFormInput => $a->reviewEvaluationFormId === $step->review_evaluation_form_id
                ? new StepEvaluationFormInput($a->reviewEvaluationFormId, $isRequired, $isActive)
                : $a,
            $structure->details[$index]->evaluationForms,
        );

        DB::transaction(function () use ($step, $isRequired, $isActive, $actor, $detail, $structure, $index, $attached): void {
            PublishPhaseVersion::run($detail->formPhase ?? $detail->formPhase()->firstOrFail(), $structure->withEvaluationFormsAt($index, $attached), $actor);

            AuditTrail::record('Pemasangan form evaluasi diubah', $step, $actor, new AuditChange(
                ['is_required' => $step->is_required, 'is_active' => $step->is_active],
                ['is_required' => $isRequired, 'is_active' => $isActive],
            ));
        });
    }
}
