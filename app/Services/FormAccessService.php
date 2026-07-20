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
}
