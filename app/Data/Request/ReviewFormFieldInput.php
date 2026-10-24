<?php

namespace App\Data\Request;

/**
 * Satu field form evaluasi dari layar Buat/Ubah, urut seperti di layar. `id` null berarti
 * field baru. Opsi null berarti kiriman tak menyebut opsi: tidak ada perubahan opsi.
 */
final class ReviewFormFieldInput
{
    /**
     * @param  array<string, int|float>|null  $validationRules  min_length, max_length, min_value, max_value
     * @param  list<array{id: int|null, label: string, value: string|null}>|null  $options
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $fieldTypeId,
        public readonly string $label,
        public readonly ?string $description,
        public readonly bool $isRequired,
        public readonly ?array $validationRules,
        public readonly ?array $options,
    ) {}
}
