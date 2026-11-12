<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;

/**
 * BR-FE-28 — Form pengajuan tiap tahap: Form langkah `opensSubmission()`, paling banyak satu per
 * tahap (lewat beberapa aturan akses tetap satu). Tahap tanpa langkah Pengajuan tidak ikut.
 */
class PhaseSubmissionForms implements ChangesNoState
{
    /**
     * @param  list<int>  $phaseIds
     * @return array<int, array{formId: int, formTitle: string, phaseTitle: string}> per id tahap, urut id tahap
     */
    public static function forPhases(array $phaseIds): array
    {
        $steps = FormPhaseDetail::query()
            ->whereIn('form_phase_id', $phaseIds)
            ->inLatestVersion()
            ->where('deadline_type', DeadlineType::Submit)
            ->whereNull('deadline_cycle')
            ->with(['formAccessControl.form:id,title', 'formPhase:id,title'])
            ->orderBy('form_phase_id')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $forms = [];

        foreach ($steps as $step) {
            $form = $step->formAccessControl?->form;

            if ($form === null || $step->formPhase === null || isset($forms[$step->form_phase_id])) {
                continue;
            }

            $forms[$step->form_phase_id] = [
                'formId' => $form->id,
                'formTitle' => $form->title,
                'phaseTitle' => $step->formPhase->title,
            ];
        }

        return $forms;
    }
}
