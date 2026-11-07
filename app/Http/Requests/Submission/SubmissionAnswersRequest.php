<?php

namespace App\Http\Requests\Submission;

use App\Data\Request\SubmissionAnswersInput;
use App\Http\Requests\Concerns\TargetsWizardStep;
use App\Http\Requests\Concerns\ValidatesFieldResponses;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isian satu langkah wizard pengajuan, untuk simpan draft maupun submit. Berkas dikirim di
 * tempat jawabannya, `responses.*.value`.
 */
class SubmissionAnswersRequest extends FormRequest
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
        ];
    }

    public function toInput(): SubmissionAnswersInput
    {
        return new SubmissionAnswersInput(
            formId: $this->integer('form_id'),
            submissionPeriodId: $this->integer('submission_period_id'),
            answers: $this->fieldAnswers(),
            uploads: $this->uploadsByField(),
            lastUpdatedAt: $this->lastUpdatedAt(),
            clearsEmptyAnswers: $this->clearsEmptyAnswers(),
        );
    }

    /** Layar legacy tidak memuat ulang jawaban tersimpan, jadi kosong berarti belum disentuh. */
    protected function clearsEmptyAnswers(): bool
    {
        return false;
    }
}
