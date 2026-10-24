<?php

namespace App\Http\Requests\Review;

use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use Illuminate\Foundation\Http\FormRequest;

/** Reviewer memulai satu form evaluasi dari halaman pengajuan. */
class StartEvaluationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'submission_id' => 'required|integer|exists:form_submissions,id',
            'review_evaluation_form_id' => 'required|integer|exists:review_evaluation_forms,id',
        ];
    }

    public function submission(): FormSubmission
    {
        return FormSubmission::findOrFail($this->integer('submission_id'));
    }

    public function evaluationForm(): ReviewEvaluationForm
    {
        return ReviewEvaluationForm::findOrFail($this->integer('review_evaluation_form_id'));
    }
}
