<?php

namespace App\Http\Requests\Concerns;

use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseDraftInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Enums\DeadlineType;

/**
 * BR-FE-33 — `phase_details` kiriman editor tahap: langkah urut seperti di layar beserta Form
 * Evaluasi terpasangnya (BR-FE-30). Dipakai buat tahap dan simpan draf; galat baris berkunci
 * `phase_details.{i}.*` supaya tampil di barisnya.
 */
trait ReadsPhaseSteps
{
    /** @return array<string, string> */
    protected function stepRules(): array
    {
        return [
            'phase_details' => 'required|array|min:1',
            'phase_details.*.form_access_control_id' => 'required|exists:form_access_controls,id',
            'phase_details.*.phase_type_id' => 'required|exists:phase_types,id',
            'phase_details.*.needs_review' => 'boolean',
            // BR-FE-07 + BR-FE-26 (ADR 0019) — langkah menyebut jenis tenggat dan siklusnya;
            // tanggalnya milik kalender periode. Kombinasi Upload Luaran diperiksa AssertPhaseSteps.
            'phase_details.*.deadline_type' => 'required|in:submit,output',
            'phase_details.*.deadline_cycle' => 'nullable|integer|min:1|max:'.DeadlineType::MAX_CYCLE,
            'phase_details.*.evaluation_forms' => 'array',
            'phase_details.*.evaluation_forms.*.review_evaluation_form_id' => 'required|integer|exists:review_evaluation_forms,id',
            'phase_details.*.evaluation_forms.*.is_required' => 'boolean',
            'phase_details.*.evaluation_forms.*.is_active' => 'boolean',
        ];
    }

    public function toSteps(): PhaseDraftInput
    {
        $steps = [];

        foreach ($this->array('phase_details') as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $at = "phase_details.{$index}";
            $steps[] = new FormPhaseDetailInput(
                id: null,
                formAccessControlId: $this->integer("{$at}.form_access_control_id"),
                phaseTypeId: $this->integer("{$at}.phase_type_id"),
                needsReview: $this->boolean("{$at}.needs_review"),
                deadlineType: DeadlineType::from($this->string("{$at}.deadline_type")->value()),
                deadlineCycle: filter_var($row['deadline_cycle'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                evaluationForms: $this->evaluationFormsAt($at),
            );
        }

        return new PhaseDraftInput($steps);
    }

    /** @return list<StepEvaluationFormInput> */
    private function evaluationFormsAt(string $at): array
    {
        $attached = [];

        foreach (array_keys($this->array("{$at}.evaluation_forms")) as $position) {
            $attached[] = new StepEvaluationFormInput(
                $this->integer("{$at}.evaluation_forms.{$position}.review_evaluation_form_id"),
                $this->boolean("{$at}.evaluation_forms.{$position}.is_required", true),
                $this->boolean("{$at}.evaluation_forms.{$position}.is_active", true),
            );
        }

        return $attached;
    }
}
