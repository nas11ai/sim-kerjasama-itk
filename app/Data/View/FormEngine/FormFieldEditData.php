<?php

namespace App\Data\View\FormEngine;

use App\Models\FormField;
use App\Models\FormFieldOption;
use Spatie\LaravelData\Data;

/**
 * Satu field di editor — terbit, atau dari draf (BR-FE-34; `id` null = field baru). `answerCount`
 * dimuat FormGeneral lewat `withCount`: begitu lebih dari nol, tipe field terkunci (BR-FE-23).
 */
final class FormFieldEditData extends Data
{
    /** @param  list<array{id: int|null, label: string}>  $options */
    public function __construct(
        public ?int $id,
        public string $label,
        public int $fieldTypeId,
        public bool $isRequired,
        public ?string $requiredSince,
        public int $answerCount,
        public array $options,
    ) {}

    // BR-FE-24 — opsi yang dibuang sudah soft delete, jadi relasi hanya membawa yang ditawarkan.
    public static function fromModel(FormField $field): self
    {
        return new self(
            id: $field->id,
            label: $field->label,
            fieldTypeId: $field->field_type_id,
            isRequired: $field->is_required,
            requiredSince: $field->required_since?->toIso8601String(),
            answerCount: $field->form_field_responses_count,
            options: array_values($field->formFieldOptions
                ->sortBy('id')
                ->map(fn (FormFieldOption $option): array => ['id' => $option->id, 'label' => $option->label])
                ->all()),
        );
    }
}
