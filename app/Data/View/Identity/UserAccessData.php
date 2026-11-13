<?php

namespace App\Data\View\Identity;

use App\Models\User;
use App\Queries\Identity\EffectivePermissionNames;
use App\Support\Identity\PermissionCatalog;
use App\Support\Identity\RoleCatalog;
use Spatie\LaravelData\Data;

/**
 * "Role & Hak Akses" OPERATOR-08-03. BR-IAM-09 — hak akses langsung dan efektif dikirim
 * terpisah: yang bisa dicabut di layar hanya yang langsung (IAM-PERM-04).
 *
 * @phpstan-type Option array{value: string, label: string}
 * @phpstan-type PermissionOption array{value: string, label: string, description: string}
 */
final class UserAccessData extends Data
{
    /**
     * @param  list<Option>  $roles
     * @param  list<PermissionOption>  $directPermissions
     * @param  list<PermissionOption>  $effectivePermissions
     * @param  list<Option>  $assignableRoles
     * @param  list<PermissionOption>  $grantablePermissions
     */
    public function __construct(
        public array $roles,
        public array $directPermissions,
        public array $effectivePermissions,
        public array $assignableRoles,
        public array $grantablePermissions,
    ) {}

    public static function fromModel(User $user): self
    {
        $direct = PermissionCatalog::namesOf($user->getDirectPermissions());

        return new self(
            roles: RoleCatalog::optionsOf($user->roles),
            directPermissions: PermissionCatalog::options($direct),
            effectivePermissions: PermissionCatalog::options(EffectivePermissionNames::run($user)),
            assignableRoles: RoleCatalog::options(RoleCatalog::ASSIGNABLE),
            grantablePermissions: PermissionCatalog::options(array_values(array_diff(array_keys(PermissionCatalog::LABELS), $direct))),
        );
    }
}
