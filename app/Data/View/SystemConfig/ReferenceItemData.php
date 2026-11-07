<?php

namespace App\Data\View\SystemConfig;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use UnexpectedValueException;

/**
 * Satu item data acuan ADMIN-08-10. `usageCount` dari ReferenceEntityUsage, angka yang sama
 * dengan penegak hapus (BR-SC-01). `isActive` null bila tabelnya tidak mengenal aktif.
 */
final class ReferenceItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?bool $isActive,
        public int $usageCount,
    ) {}

    public static function fromRecord(Model $record, bool $deactivatable, int $usageCount): self
    {
        $id = $record->getKey();
        $name = $record->getAttribute('name');

        if (!is_int($id) || !is_string($name)) {
            throw new UnexpectedValueException('Data acuan wajib ber-id bilangan bulat dan bernama teks.');
        }

        return new self(
            id: $id,
            name: $name,
            isActive: $deactivatable ? $record->getAttribute('is_active') === true : null,
            usageCount: $usageCount,
        );
    }
}
