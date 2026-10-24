<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Permission;

/**
 * Nama permission efektif seorang user (langsung dan lewat role), urut abjad.
 *
 * @method static list<string> run(User $user)
 */
class EffectivePermissionNames implements ChangesNoState
{
    use AsAction;

    /** @return list<string> */
    public function handle(User $user): array
    {
        // Super Admin lolos lewat Gate::before tanpa permission langsung (ADR 0015); sidebar butuh daftar eksplisit.
        $permissions = $user->hasRole('Super Admin')
            ? Permission::query()->get()
            : $user->getAllPermissions();

        $names = [];
        foreach ($permissions as $permission) {
            if ($permission instanceof Permission) {
                $names[] = $permission->name;
            }
        }
        sort($names);

        return $names;
    }
}
