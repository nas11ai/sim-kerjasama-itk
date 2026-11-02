<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-09 — hanya hak akses **langsung** yang dicabut. Yang datang dari role tetap,
 * termasuk bila satu hak akses datang dari dua sumber sekaligus (IAM-PERM-04).
 *
 * @method static void run(User $actor, User $user, string $permission)
 */
class RevokeDirectPermission
{
    use AsAction;

    /** @throws ValidationException */
    public function handle(User $actor, User $user, string $permission): void
    {
        PermissionCatalog::guardKnown($permission);

        $user->revokePermissionTo($permission);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Hak akses langsung dicabut', $user, $actor, new AuditChange(old: ['permission' => $permission]));
    }
}
