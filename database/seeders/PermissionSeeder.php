<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Canonical Spatie permission catalog (M1 / DDD Identity & Access).
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'submissions.create',
        'submissions.view-own',
        'submissions.view-all',
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
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Preserve existing role grants for permissions already used by admin routes (#176).
        // Full role→permission matrix is a separate task.
        $operator = Role::findOrCreate('operator');
        $admin = Role::findOrCreate('admin');
        $legacyAdmin = Role::findOrCreate('Admin');
        Role::findOrCreate('researcher');

        $operator->givePermissionTo([
            'reporting.export',
            'reporting.view-audit-log',
        ]);

        $admin->givePermissionTo([
            'reporting.export',
            'reporting.view-audit-log',
            'users.manage',
        ]);

        $legacyAdmin->givePermissionTo([
            'reporting.export',
            'reporting.view-audit-log',
            'users.manage',
        ]);

        $this->command->info('✓ PermissionSeeder completed successfully.');
    }
}
