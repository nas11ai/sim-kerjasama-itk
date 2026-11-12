<?php

namespace App\Data\View\FormEngine;

use App\Data\Request\ReviewFormFieldInput;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldOption;
use Spatie\LaravelData\Data;

/**
 * Satu field di editor pustaka Form Evaluasi, dari draf (BR-FE-31) atau versi terbit terakhir.
 * Tanpa id: Terbitkan menyalin baris baru (BR-REV-18), jadi tipe field tidak perlu dikunci —
 * jawaban lama tetap menunjuk field versinya.
 */
final class EvaluationFieldEditData extends Data
{
    /**
     * @param  array{minLength: int|null, maxLength: int|null, minValue: float|null, maxValue: float|null}  $limits
     * @param  list<array{label: string, value: string|null}>  $options
     */
    public function __construct(
        public string $label,
        public ?string $description,
        public int $fieldTypeId,
        public bool $isRequired,
        public array $limits,
        public array $options,
    ) {}

    // Dimuat EvaluationFormEdit: opsi urut `order`.
    public static function fromModel(ReviewFormField $field): self
    {
        return new self(
            label: $field->label,
            description: $field->description,
            fieldTypeId: $field->field_type_id,
            isRequired: $field->is_required,
            limits: self::limits($field->validation_rules ?? []),
            options: array_values($field->reviewFormFieldOptions
                ->map(fn (ReviewFormFieldOption $option): array => ['label' => $option->label, 'value' => $option->value])
                ->all()),
        );
    }

    public static function fromInput(ReviewFormFieldInput $field): self
    {
        return new self(
            label: $field->label,
            description: $field->description,
            fieldTypeId: $field->fieldTypeId,
            isRequired: $field->isRequired,
            limits: self::limits($field->validationRules ?? []),
            options: array_map(
                fn (array $option): array => ['label' => $option['label'], 'value' => $option['value']],
                $field->options ?? [],
            ),
        );
    }

    /**
     * @param  array<string, scalar|null>  $rules
     * @return array{minLength: int|null, maxLength: int|null, minValue: float|null, maxValue: float|null}
     */
    private static function limits(array $rules): array
    {
        return [
            'minLength' => filter_var($rules['min_length'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'maxLength' => filter_var($rules['max_length'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'minValue' => filter_var($rules['min_value'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'maxValue' => filter_var($rules['max_value'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
        ];
    }
}
