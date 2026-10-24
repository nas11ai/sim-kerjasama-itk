<?php

namespace App\Http\Requests\Concerns;

/** Sasaran langkah wizard pengajuan: form, periode, dan fase yang sedang dibuka peneliti. */
trait TargetsWizardStep
{
    /** @return array<string, string> */
    private function wizardStepRules(): array
    {
        return [
            'form_id' => 'required|exists:forms,id',
            'submission_period_id' => 'required|exists:submission_periods,id',
            'form_phase_id' => 'required|exists:form_phases,id',
        ];
    }
}
