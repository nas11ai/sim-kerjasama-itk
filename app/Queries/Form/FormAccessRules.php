<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\FormAccessRuleData;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Queries\Identity\OrganizationOptions;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Aturan akses satu Form (ADMIN-08-07), urut permission lalu nama organisasi. `forForms`
 * memberi aturan beberapa Form sekaligus untuk pilihan akses detail tahap (W102).
 *
 * @method static list<FormAccessRuleData> run(Form $form)
 */
class FormAccessRules implements ChangesNoState
{
    use AsAction;

    /** @return list<FormAccessRuleData> */
    public function handle(Form $form): array
    {
        return self::forForms([$form->id])[$form->id] ?? [];
    }

    /**
     * Aturan akses beberapa Form sekaligus, ber-key id Form; urutan sama dengan `handle`.
     *
     * @param  list<int>  $formIds
     * @return array<int, list<FormAccessRuleData>>
     */
    public static function forForms(array $formIds): array
    {
        $rules = FormAccessControl::query()
            ->whereIn('form_id', $formIds)
            // BR-FE-32 — tahap yang memakainya, bukan baris langkah (yang bertambah tiap terbit).
            ->withCount(['formPhaseDetails as phase_count' => fn (Builder $steps) => $steps->select(DB::raw('count(distinct form_phase_id)'))])
            ->with('organization:id,name')
            ->get()
            ->sortBy([
                ['permission', 'asc'],
                ['organization.name', 'asc'],
            ]);

        $withChildren = self::organizationsWithActiveChildren($rules);
        $grouped = [];

        foreach ($rules as $rule) {
            $grouped[$rule->form_id][] = new FormAccessRuleData(
                id: $rule->id,
                permission: $rule->permission,
                permissionLabel: PermissionCatalog::label($rule->permission),
                organizationLabel: OrganizationOptions::label(
                    $rule->organization->name ?? '',
                    in_array($rule->organization_id, $withChildren, true),
                ),
                phaseCount: $rule->phase_count,
            );
        }

        return $grouped;
    }

    /**
     * @param  Collection<int, FormAccessControl>  $rules
     * @return array<int, int>
     */
    private static function organizationsWithActiveChildren(Collection $rules): array
    {
        return Organization::query()
            ->where('is_active', true)
            ->whereIn('parent_id', $rules->pluck('organization_id'))
            ->distinct()
            ->get(['parent_id'])
            ->map(static fn (Organization $organization): int => $organization->parent_id ?? 0)
            ->all();
    }
}
