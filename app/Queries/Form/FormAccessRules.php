<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\FormAccessRuleData;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Queries\Identity\OrganizationOptions;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Aturan akses satu Form (ADMIN-08-07), urut permission lalu nama organisasi.
 *
 * @method static list<FormAccessRuleData> run(Form $form)
 */
class FormAccessRules implements ChangesNoState
{
    use AsAction;

    /** @return list<FormAccessRuleData> */
    public function handle(Form $form): array
    {
        $rules = FormAccessControl::query()
            ->where('form_id', $form->id)
            ->withCount('formPhaseDetails')
            ->with('organization:id,name')
            ->get()
            ->sortBy([
                ['permission', 'asc'],
                ['organization.name', 'asc'],
            ]);

        $withChildren = $this->organizationsWithActiveChildren($rules);

        return array_values($rules->map(fn (FormAccessControl $rule): FormAccessRuleData => new FormAccessRuleData(
            id: $rule->id,
            permission: $rule->permission,
            permissionLabel: PermissionCatalog::label($rule->permission),
            organizationLabel: OrganizationOptions::label(
                $rule->organization->name ?? '',
                in_array($rule->organization_id, $withChildren, true),
            ),
            phaseDetailCount: $rule->form_phase_details_count,
        ))->all());
    }

    /**
     * @param  Collection<int, FormAccessControl>  $rules
     * @return array<int, int>
     */
    private function organizationsWithActiveChildren(Collection $rules): array
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
