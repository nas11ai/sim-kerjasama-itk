<?php

namespace App\Data\Request;

/**
 * BR-FE-31 — isi draf Formulir Evaluasi: field urut seperti di layar. Disimpan sebagai JSON di
 * `configuration_drafts.payload` tanpa id field/opsi: Terbitkan menyalin baris baru (BR-REV-18),
 * jadi id lintas versi tidak bermakna. Payload hanya ditulis toPayload(), jadi bentuknya pasti.
 *
 * @phpstan-type FieldPayload array{field_type_id: int, label: string, description: string|null, is_required: bool, validation_rules: array<string, int|float>|null, options: list<array{label: string, value: string|null}>|null}
 * @phpstan-type DraftPayload array{fields: list<FieldPayload>}
 */
final class EvaluationDraftInput
{
    /** @param  list<ReviewFormFieldInput>  $fields */
    public function __construct(public readonly array $fields) {}

    /** @return DraftPayload */
    public function toPayload(): array
    {
        return ['fields' => array_map(fn (ReviewFormFieldInput $field): array => [
            'field_type_id' => $field->fieldTypeId,
            'label' => $field->label,
            'description' => $field->description,
            'is_required' => $field->isRequired,
            'validation_rules' => $field->validationRules,
            'options' => $field->options === null ? null : array_map(
                fn (array $option): array => ['label' => $option['label'], 'value' => $option['value']],
                $field->options,
            ),
        ], $this->fields)];
    }

    /** @param  DraftPayload  $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(array_map(self::field(...), $payload['fields']));
    }

    /** @param  FieldPayload  $field */
    private static function field(array $field): ReviewFormFieldInput
    {
        return new ReviewFormFieldInput(
            id: null,
            fieldTypeId: $field['field_type_id'],
            label: $field['label'],
            description: $field['description'],
            isRequired: $field['is_required'],
            validationRules: $field['validation_rules'],
            options: $field['options'] === null ? null : array_map(
                fn (array $option): array => ['id' => null, 'label' => $option['label'], 'value' => $option['value']],
                $field['options'],
            ),
        );
    }
}
