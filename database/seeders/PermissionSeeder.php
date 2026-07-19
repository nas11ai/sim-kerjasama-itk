<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $operator = Role::findOrCreate('operator');
        $admin = Role::findOrCreate('admin');
        $researcher = Role::findOrCreate('researcher');

        Permission::findOrCreate('reporting.export');
        Permission::findOrCreate('reporting.view-audit-log');
        Permission::findOrCreate('users.manage');

        $operator->givePermissionTo([
            'reporting.export',
            'reporting.view-audit-log',
        ]);

        $admin->givePermissionTo([
            'reporting.export',
            'reporting.view-audit-log',
            'users.manage',
        ]);

        $legacyAdmin = Role::findOrCreate('Admin');
        $legacyAdmin->givePermissionTo([
            'reporting.export',
            'reporting.view-audit-log',
            'users.manage',
        ]);

        $this->command->info('✓ PermissionSeeder completed successfully.');
    }
}
