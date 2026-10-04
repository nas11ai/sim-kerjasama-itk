<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\FormPhaseInput;
use Illuminate\Foundation\Http\FormRequest;

class FormPhaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'phase_details' => 'required|array|min:1',
            'phase_details.*.id' => 'nullable|integer|distinct',
            'phase_details.*.form_access_control_id' => 'required|exists:form_access_controls,id',
            'phase_details.*.phase_type_id' => 'required|exists:phase_types,id',
            'phase_details.*.order' => 'required|integer|min:1',
            'phase_details.*.needs_review' => 'boolean',
            // BR-FE-07 — kolomnya NOT NULL; detail lama menyimpan tenggatnya sendiri,
            // sedangkan saat membuat semua baris baru.
            'phase_details.*.submission_date_id' => ($this->isMethod('POST')
                ? 'required'
                : 'nullable|required_without:phase_details.*.id')
                .'|integer|exists:submission_dates,id',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phase_details.*.submission_date_id.required_without' => 'Detail baru wajib punya tenggat.',
            'phase_details.*.submission_date_id.required' => 'Detail wajib punya tenggat.',
        ];
    }

    public function toInput(): FormPhaseInput
    {
        $details = [];

        foreach ($this->array('phase_details') as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $details[] = new FormPhaseDetailInput(
                id: filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                formAccessControlId: intval($row['form_access_control_id'] ?? 0),
                phaseTypeId: intval($row['phase_type_id'] ?? 0),
                needsReview: $this->boolean("phase_details.{$index}.needs_review"),
                submissionDateId: filter_var(
                    $row['submission_date_id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE,
                ),
            );
        }

        return new FormPhaseInput(
            title: $this->string('title')->value(),
            description: $this->filled('description') ? $this->string('description')->value() : null,
            isActive: $this->boolean('is_active'),
            details: $details,
        );
    }
}
