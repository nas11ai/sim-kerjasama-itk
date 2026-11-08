<?php

namespace App\Http\Requests\Submission;

use App\Data\Request\SubmissionAnswersInput;
use App\Data\Request\WizardStepInput;
use App\Http\Requests\Concerns\TargetsWizardStep;
use App\Http\Requests\Concerns\ValidatesFieldResponses;
use App\Models\FormSubmission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Perpindahan langkah wizard v2: jawaban langkah yang ditinggalkan (boleh kosong) dan langkah
 * tujuannya. Form, periode, dan fase dibaca dari pengajuan di route, bukan dari klien. Berkas
 * dikirim di tempat jawabannya, `responses.*.value`.
 */
class WizardStepRequest extends FormRequest
{
    use TargetsWizardStep;
    use ValidatesFieldResponses;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            ...$this->wizardStepRules(),
            ...$this->responseRules(),
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

    private function toInput(): SubmissionAnswersInput
    {
        return new SubmissionAnswersInput(
            formId: $this->integer('form_id'),
            submissionPeriodId: $this->integer('submission_period_id'),
            answers: $this->fieldAnswers(),
            uploads: $this->uploadsByField(),
            lastUpdatedAt: $this->lastUpdatedAt(),
        );
    }
}
