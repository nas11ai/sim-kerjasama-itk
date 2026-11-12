<?php

namespace App\Data\Request;

/**
 * BR-FE-34 — isi draf field Form, urut seperti di layar. Berbeda dengan draf berversi, id field dan
 * opsi terbit ikut tersimpan: Form tidak berversi, jadi Terbitkan memperbarui baris yang sama dan
 * jawaban lama tetap menempel (BR-FE-23). Payload hanya ditulis toPayload(), jadi bentuknya pasti.
 *
 * @phpstan-type OptionPayload array{id: int|null, label: string}
 * @phpstan-type FieldPayload array{id: int|null, field_type_id: int, label: string, is_required: bool, options: list<OptionPayload>|null}
 * @phpstan-type FormDraftPayload array{fields: list<FieldPayload>}
 */
final class FormDraftInput
{
    /** @param  list<FormFieldInput>  $fields */
    public function __construct(public readonly array $fields) {}

    /** @return list<int> */
    public function fieldTypeIds(): array
    {
        return array_map(fn (FormFieldInput $field): int => $field->fieldTypeId, $this->fields);
    }

    /** @return FormDraftPayload */
    public function toPayload(): array
    {
        return ['fields' => array_map(fn (FormFieldInput $field): array => [
            'id' => $field->id,
            'field_type_id' => $field->fieldTypeId,
            'label' => $field->label,
            'is_required' => $field->isRequired,
            'options' => $field->options,
        ], $this->fields)];
    }

    /** @param  FormDraftPayload  $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(array_map(fn (array $field): FormFieldInput => new FormFieldInput(
            id: $field['id'],
            fieldTypeId: $field['field_type_id'],
            label: $field['label'],
            isRequired: $field['is_required'],
            options: $field['options'],
        ), $payload['fields']));
    }
}
