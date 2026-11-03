<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Organisasi aktif sebagai pilihan, depth-first dengan nama terurut per tingkat. Satu kueri;
 * pohonnya dibentuk di PHP.
 *
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static list<Option> run()
 */
class OrganizationOptions implements ChangesNoState
{
    use AsAction;

    /** ADR 0013 — aturan akses pada organisasi juga berlaku bagi seluruh unit di bawahnya. */
    public static function label(string $name, bool $hasActiveChildren): string
    {
        return $hasActiveChildren ? $name.' — beserta unit di bawahnya' : $name;
    }

    /** @return list<Option> */
    public function handle(): array
    {
        $active = Organization::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'parent_id']);
        $byParent = $active->groupBy(static fn (Organization $organization): int => $organization->parent_id ?? 0);
        $roots = $active->filter(static fn (Organization $organization): bool => !$active->contains('id', $organization->parent_id));

        return $this->flatten($roots->values(), $byParent->all());
    }

    /**
     * @param  Collection<int, Organization>  $level
     * @param  array<Collection<int, Organization>>  $byParent
     * @return list<Option>
     */
    private function flatten(Collection $level, array $byParent): array
    {
        $options = [];

        foreach ($level as $organization) {
            $children = $byParent[$organization->id] ?? new Collection;
            $options[] = ['value' => (string) $organization->id, 'label' => self::label($organization->name, $children->isNotEmpty())];
            array_push($options, ...$this->flatten($children, $byParent));
        }

        return $options;
    }
}
