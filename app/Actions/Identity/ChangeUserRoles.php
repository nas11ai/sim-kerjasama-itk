<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Identity\RoleCatalog;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-09, W57 — mengubah peran yang boleh disunting langsung (`RoleCatalog::ASSIGNABLE`).
 * Peran reviewer hanya lewat penunjukan (BR-IAM-11) dan role legacy tidak tersentuh, supaya
 * record `reviewers` dan role-nya tidak menyimpang. Hak akses langsung tidak ikut berubah.
 *
 * @method static User run(User $actor, User $user, list<string> $roles)
 */
class ChangeUserRoles
{
    use AsAction;

    /**
     * @param  list<string>  $roles
     *
     * @throws ValidationException
     */
    public function handle(User $actor, User $user, array $roles): User
    {
        $requested = array_values(array_unique($roles));
        sort($requested);

        if (array_diff($requested, RoleCatalog::ASSIGNABLE) !== []) {
            throw ValidationException::withMessages([
                'roles' => 'Peran reviewer diberikan lewat penunjukan reviewer, bukan di sini.',
            ]);
        }

        $current = RoleCatalog::namesOf($user->roles);
        $before = array_values(array_intersect($current, RoleCatalog::ASSIGNABLE));
        $untouched = array_values(array_diff($current, RoleCatalog::ASSIGNABLE));

        $user->syncRoles([...$untouched, ...$requested]);
        $changed = $user->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Peran user diubah', $changed, $actor, new AuditChange(
            old: ['roles' => $before],
            new: ['roles' => $requested],
        ));

        return $changed;
    }
}
