<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-09 — hak akses langsung **menambah** yang datang dari role, tidak menggantikannya.
 *
 * @method static void run(User $actor, User $user, string $permission)
 */
class GrantDirectPermission
{
    use AsAction;

    /** @throws ValidationException */
    public function handle(User $actor, User $user, string $permission): void
    {
        PermissionCatalog::guardKnown($permission);

        $user->givePermissionTo($permission);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Hak akses langsung diberikan', $user, $actor, new AuditChange(new: ['permission' => $permission]));
    }
}
