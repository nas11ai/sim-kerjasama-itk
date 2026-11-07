<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\SystemConfig\OrganizationNodeData;
use App\Models\Organization;
use App\Support\Identity\OrganizationTypeCatalog;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Seluruh unit, aktif dan nonaktif, depth-first dengan nama terurut per tingkat (ADMIN-08-02).
 * Tidak ada unit yang hilang: yang tak terjangkau dari akar mana pun ikut tampil sebagai akar.
 *
 * @method static list<OrganizationNodeData> run()
 */
class OrganizationTree implements ChangesNoState
{
    use AsAction;

    /** @return list<OrganizationNodeData> */
    public function handle(): array
    {
        $units = Organization::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'type', 'is_active', 'parent_id']);
        $byId = $units->keyBy('id');
        $byParent = $units->groupBy(static fn (Organization $unit): int => $unit->parent_id ?? 0)->all();
        $roots = $units->filter(static fn (Organization $unit): bool => $unit->parent_id === null || !$byId->has($unit->parent_id));

        $visited = [];
        $nodes = $this->walk($roots->values(), $byParent, 0, $visited);

        // Data lama bersiklus tidak punya akar; unit pertamanya menjadi akar cabangnya.
        foreach ($units as $unit) {
            array_push($nodes, ...$this->walk(new Collection([$unit]), $byParent, 0, $visited));
        }

        return $nodes;
    }

    /**
     * @param  Collection<int, Organization>  $level
     * @param  array<Collection<int, Organization>>  $byParent
     * @param  array<int, true>  $visited
     * @return list<OrganizationNodeData>
     */
    private function walk(Collection $level, array $byParent, int $depth, array &$visited): array
    {
        $nodes = [];

        foreach ($level as $unit) {
            if (isset($visited[$unit->id])) {
                continue;
            }

            $visited[$unit->id] = true;
            $children = ($byParent[$unit->id] ?? new Collection)
                ->reject(static fn (Organization $child): bool => isset($visited[$child->id]))
                ->values();
            $nodes[] = new OrganizationNodeData(
                id: $unit->id,
                name: $unit->name,
                typeLabel: OrganizationTypeCatalog::label($unit->type),
                isActive: $unit->is_active,
                parentId: $unit->parent_id,
                depth: $depth,
                hasChildren: $children->isNotEmpty(),
            );
            array_push($nodes, ...$this->walk($children, $byParent, $depth + 1, $visited));
        }

        return $nodes;
    }
}
