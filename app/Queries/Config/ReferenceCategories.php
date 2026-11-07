<?php

namespace App\Queries\Config;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\SystemConfig\ReferenceCategoryData;
use App\Data\View\SystemConfig\ReferenceItemData;
use App\Support\Config\ReferenceEntity;
use App\Support\Config\ReferenceEntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ADMIN-08-10 — semua kategori data acuan beserta itemnya, urut registry.
 *
 * @method static list<ReferenceCategoryData> run()
 */
class ReferenceCategories implements ChangesNoState
{
    use AsAction;

    /** @return list<ReferenceCategoryData> */
    public function handle(): array
    {
        return array_values(array_map($this->category(...), ReferenceEntityRegistry::all()));
    }

    /** @param  ReferenceEntity<covariant Model>  $entity */
    private function category(ReferenceEntity $entity): ReferenceCategoryData
    {
        return new ReferenceCategoryData(
            key: $entity->key,
            label: $entity->label,
            group: $entity->group,
            usedBy: $entity->usedBy,
            editable: $entity->editable,
            deactivatable: $entity->deactivatable,
            items: array_map(
                fn (array $row): ReferenceItemData => ReferenceItemData::fromRecord($row['record'], $entity->deactivatable, $row['usageCount']),
                ReferenceEntityUsage::forAll($entity),
            ),
        );
    }
}
