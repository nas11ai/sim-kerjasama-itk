<?php

namespace App\Data\View\FormEngine;

use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldOption;
use Spatie\LaravelData\Data;

/**
 * Satu field form evaluasi di layar sunting tahap. `answerCount` > 0 mengunci tipe dan hapus
 * (cermin BR-FE-23, W103) — frontend tidak menghitungnya. Opsi yang dibuang sudah soft delete.
 */
final class EvaluationFieldEditData extends Data
{
    /**
     * @param  array{minLength: int|null, maxLength: int|null, minValue: float|null, maxValue: float|null}  $limits
     * @param  list<array{id: int, label: string, value: string|null}>  $options
     */
    public function __construct(
        public int $id,
        public string $label,
        public ?string $description,
        public int $fieldTypeId,
        public bool $isRequired,
        public array $limits,
        public int $answerCount,
        public array $options,
    ) {}

    // Dimuat PhaseEvaluationForms: `withCount` jawaban dan opsi urut `order`.
    public static function fromModel(ReviewFormField $field): self
    {
        $rules = $field->validation_rules ?? [];

        return new self(
            id: $field->id,
            label: $field->label,
            description: $field->description,
            fieldTypeId: $field->field_type_id,
            isRequired: $field->is_required,
            limits: [
                'minLength' => filter_var($rules['min_length'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                'maxLength' => filter_var($rules['max_length'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                'minValue' => filter_var($rules['min_value'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
                'maxValue' => filter_var($rules['max_value'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            ],
            answerCount: $field->review_form_field_responses_count ?? 0,
            options: array_values($field->reviewFormFieldOptions
                ->map(fn (ReviewFormFieldOption $option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'value' => $option->value,
                ])
                ->all()),
        );
    }
}
