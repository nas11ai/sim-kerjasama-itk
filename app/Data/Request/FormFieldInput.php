<?php

namespace App\Data\Request;

/**
 * Satu field dari layar Form, urut seperti di layar. `id` null berarti field baru. Opsi null
 * berarti kiriman tak menyebut opsi — "tidak ada perubahan", bukan "hapus semua" (BR-FE-24).
 */
final class FormFieldInput
{
    /**
     * @param  list<array{id: int|null, label: string}>|null  $options
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $fieldTypeId,
        public readonly string $label,
        public readonly bool $isRequired,
        public readonly ?array $options,
    ) {}
}
