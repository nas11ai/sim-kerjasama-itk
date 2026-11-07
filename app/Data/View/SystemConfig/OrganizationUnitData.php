<?php

namespace App\Data\View\SystemConfig;

use App\Data\Result\OrganizationCensusData;
use App\Models\Organization;
use App\Support\Identity\OrganizationTypeCatalog;
use Spatie\LaravelData\Data;

/** Panel "Unit Terpilih" ADMIN-08-02; kelayakan tombol dibaca dari sensus penjaga Action. */
final class OrganizationUnitData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public string $typeLabel,
        public bool $isActive,
        public ?int $parentId,
        public OrganizationCensusData $census,
        public bool $canDeactivate,
        public bool $canDelete,
    ) {}

    public static function of(Organization $unit, OrganizationCensusData $census): self
    {
        return new self(
            id: $unit->id,
            name: $unit->name,
            type: $unit->type,
            typeLabel: OrganizationTypeCatalog::label($unit->type),
            isActive: $unit->is_active,
            parentId: $unit->parent_id,
            census: $census,
            canDeactivate: $unit->is_active && !$census->blocksDeactivation(),
            canDelete: !$census->blocksDeletion(),
        );
    }
}
