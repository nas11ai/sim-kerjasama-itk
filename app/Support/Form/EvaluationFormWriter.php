<?php

namespace App\Support\Form;

use App\Data\Request\EvaluationFormInput;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;

/**
 * Form evaluasi beserta field dan opsinya untuk satu detail tahap, dibuat berurutan.
 * Dipanggil dari dalam transaksi BuildCompleteForm, yang menulis jejak auditnya.
 */
final class EvaluationFormWriter
{
    /** @param  list<EvaluationFormInput>  $evaluationForms */
    public static function write(FormPhaseDetail $detail, array $evaluationForms): void
    {
        foreach ($evaluationForms as $index => $evaluation) {
            $created = ReviewEvaluationForm::create([
                'title' => $evaluation->title,
                'description' => $evaluation->description,
                'form_phase_detail_id' => $detail->id,
                'is_required' => $evaluation->isRequired,
                'is_active' => true,
                'order' => $index + 1,
            ]);

            foreach ($evaluation->fields as $fieldIndex => $field) {
                $reviewField = $created->reviewFormFields()->create([
                    'field_type_id' => $field->fieldTypeId,
                    'label' => $field->label,
                    'description' => $field->description,
                    'is_required' => $field->isRequired,
                    'order' => $fieldIndex + 1,
                ]);

                foreach ($field->options as $optionIndex => $option) {
                    $reviewField->reviewFormFieldOptions()->create([...$option, 'order' => $optionIndex + 1]);
                }
            }
        }
    }
}
