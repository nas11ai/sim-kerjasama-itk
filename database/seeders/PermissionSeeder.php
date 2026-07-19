<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public const GUARD = 'web';

    /**
     * Canonical Spatie permission catalog (M1 / DDD Identity & Access).
     * Includes submissions.view-assigned required by reviewer roles (#178).
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'submissions.create',
        'submissions.view-own',
        'submissions.view-all',
        'submissions.view-assigned',
        'budget.edit',
        'members.manage',
        'reviewers.assign',
        'reviewers.evaluate',
        'reviewers.view-scores-others',
        'periods.manage',
        'schemes.manage',
        'outputs.manage',
        'users.verify',
        'users.manage',
        'reporting.export',
        'reporting.view-audit-log',
    ];

    /**
     * DDD role → permission matrix (#178).
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'researcher' => [
            'submissions.create',
            'submissions.view-own',
            'budget.edit',
            'members.manage',
            'outputs.manage',
        ],
        'reviewer_internal' => [
            'reviewers.evaluate',
            'submissions.view-assigned',
            'reviewers.view-scores-others',
        ],
        'reviewer_external' => [
            'reviewers.evaluate',
            'submissions.view-assigned',
        ],
        'operator' => [
            'submissions.view-all',
            'reviewers.assign',
            'periods.manage',
            'users.verify',
            'reporting.export',
            'reporting.view-audit-log',
        ],
        // admin is synced to the full catalog in run()
        'admin' => [],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = self::GUARD;

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, $guard);

            if ($roleName === 'admin') {
                $role->syncPermissions(self::PERMISSIONS);

                continue;
            }

            $role->syncPermissions($permissions);
        }

        // Legacy Admin role (capital A) used by existing accounts / #176 routes.
        Role::findOrCreate('Admin', $guard)->syncPermissions(self::PERMISSIONS);

        $this->command->info('✓ PermissionSeeder completed successfully.');
    }
}
