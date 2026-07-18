<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\User;

class FormAccessService
{
    public function canAccessForm(User $user, Form $form): bool
    {
        return FormAccessControl::query()
            ->where('form_id', $form->id)
            ->accessibleBy($user)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function permissionNamesFor(User $user): array
    {
        return FormAccessControl::permissionNamesFor($user);
    }

    /**
     * @return list<int>
     */
    public function organizationSubtreeFor(User $user): array
    {
        return FormAccessControl::organizationSubtreeFor($user);
    }

    public function canAccessFormAccessControl(User $user, FormAccessControl $control): bool
    {
        $permissions = $this->permissionNamesFor($user);
        $subtree = $this->organizationSubtreeFor($user);

        if ($permissions === [] || $subtree === []) {
            return false;
        }

        return in_array($control->permission, $permissions, true)
            && in_array((int) $control->organization_id, $subtree, true);
    }
}
