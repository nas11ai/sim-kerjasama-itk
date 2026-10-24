<?php

namespace App\Http\Requests\Submission;

use App\Http\Requests\Concerns\TargetsWizardStep;
use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;

/** Langkah 1 wizard pengajuan (ADR 0012): peneliti membuka satu form di satu fase periode. */
class StartSubmissionRequest extends FormRequest
{
    use TargetsWizardStep;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return $this->wizardStepRules();
    }

    public function form(): Form
    {
        return Form::findOrFail($this->integer('form_id'));
    }
}
