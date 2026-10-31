<?php

namespace App\Http\Requests\Submission;

use App\Data\Request\WizardStepInput;
use App\Models\FormSubmission;

/**
 * Perpindahan langkah wizard v2: jawaban langkah yang ditinggalkan (boleh kosong) dan langkah
 * tujuannya. Form, periode, dan fase dibaca dari pengajuan di route, bukan dari klien.
 */
class WizardStepRequest extends SubmissionAnswersRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Langkah tanpa field (4, 5, 7) dan perpindahan tanpa perubahan tidak membawa jawaban.
            'responses' => 'nullable|array',
            'goto' => 'required|integer|between:1,7',
        ];
    }

    protected function prepareForValidation(): void
    {
        $submission = $this->route('submission');

        if (!$submission instanceof FormSubmission) {
            return;
        }

        $this->merge([
            'form_id' => $submission->form_id,
            'submission_period_id' => $submission->submission_period_id,
            'form_phase_id' => $submission->formPhaseDetail?->form_phase_id,
        ]);
    }

    public function toStepInput(int $step): WizardStepInput
    {
        return new WizardStepInput($this->toInput(), $step, $this->integer('goto'));
    }

    /** Wizard v2 memuat ulang jawaban tersimpan, jadi field yang dikosongkan memang dikosongkan. */
    protected function clearsEmptyAnswers(): bool
    {
        return true;
    }
}
