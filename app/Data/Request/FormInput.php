<?php

namespace App\Data\Request;

/**
 * Kartu General layar Buat/Ubah Form — disunting langsung. Field Form lewat draf (BR-FE-34,
 * FormDraftInput).
 */
final class FormInput
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $formTypeId,
        public readonly bool $isActive,
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
}
