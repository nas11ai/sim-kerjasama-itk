<?php

namespace App\Data\Request;

/**
 * Isi layar Buat/Ubah Form. Field null berarti kiriman tak menyebut field sama sekali:
 * tidak ada perubahan field, sama seperti opsi pada FormFieldInput.
 */
final class FormInput
{
    /**
     * @param  list<FormFieldInput>|null  $fields
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $formTypeId,
        public readonly bool $isActive,
        public readonly ?array $fields,
    ) {}

    /** @return array{title: string, description: string|null, form_type_id: int, is_active: bool} */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'form_type_id' => $this->formTypeId,
            'is_active' => $this->isActive,
        ];
    }

    /** @return list<int> */
    public function fieldTypeIds(): array
    {
        return array_map(fn (FormFieldInput $field): int => $field->fieldTypeId, $this->fields ?? []);
    }
}
