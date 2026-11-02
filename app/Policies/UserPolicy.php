<?php

namespace App\Policies;

use App\Models\User;

/**
 * W47 — melihat user: users.verify atau users.manage. Mengubah akun: hanya users.manage,
 * tidak atas diri sendiri dan tidak atas akun Super Admin (BR-IAM-05, BR-IAM-09, W57).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canAny(['users.verify', 'users.manage']);
    }

    public function view(User $actor, User $user): bool
    {
        return $this->viewAny($actor);
    }

    public function deactivate(User $actor, User $user): bool
    {
        return $this->administers($actor, $user);
    }

    public function changeRoles(User $actor, User $user): bool
    {
        return $this->administers($actor, $user);
    }

    public function managePermissions(User $actor, User $user): bool
    {
        return $this->administers($actor, $user);
    }

    /** BR-IAM-21 — mengakhiri seluruh sesi user lain. */
    public function endSessions(User $actor, User $user): bool
    {
        return $this->administers($actor, $user);
    }

    private function administers(User $actor, User $user): bool
    {
        if (!$actor->can('users.manage') || $actor->is($user)) {
            return false;
        }

        return !$user->hasRole('Super Admin');
    }
}
