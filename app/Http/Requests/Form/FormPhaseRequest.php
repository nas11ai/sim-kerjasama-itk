<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\FormPhaseInput;
use App\Enums\DeadlineType;
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
            // BR-FE-07 + BR-FE-26 (ADR 0019) — langkah menyebut jenis tenggat dan siklusnya;
            // tanggalnya milik kalender periode. Kombinasi Upload Luaran diperiksa AssertPhaseSteps.
            'phase_details.*.deadline_type' => 'required|in:submit,output',
            'phase_details.*.deadline_cycle' => 'nullable|integer|min:1|max:'.DeadlineType::MAX_CYCLE,
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
                formAccessControlId: $this->integer("phase_details.{$index}.form_access_control_id"),
                phaseTypeId: $this->integer("phase_details.{$index}.phase_type_id"),
                needsReview: $this->boolean("phase_details.{$index}.needs_review"),
                deadlineType: DeadlineType::from($this->string("phase_details.{$index}.deadline_type")->value()),
                deadlineCycle: filter_var($row['deadline_cycle'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
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
