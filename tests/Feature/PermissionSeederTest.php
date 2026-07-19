<?php

use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

test('PermissionSeeder creates all canonical permissions including view-assigned', function () {
    $this->seed(PermissionSeeder::class);

    $names = Permission::query()
        ->where('guard_name', 'web')
        ->whereIn('name', PermissionSeeder::PERMISSIONS)
        ->pluck('name')
        ->all();

    expect($names)->toHaveCount(count(PermissionSeeder::PERMISSIONS))
        ->and($names)->toEqualCanonicalizing(PermissionSeeder::PERMISSIONS);
});

test('PermissionSeeder assigns DDD role permission sets', function () {
    $this->seed(PermissionSeeder::class);

    foreach (PermissionSeeder::ROLE_PERMISSIONS as $roleName => $expected) {
        $role = Role::findByName($roleName);
        $actual = $role->permissions->pluck('name')->all();

        if ($roleName === 'admin') {
            expect($actual)->toHaveCount(count(PermissionSeeder::PERMISSIONS))
                ->and($actual)->toEqualCanonicalizing(PermissionSeeder::PERMISSIONS);

            continue;
        }

        expect($actual)->toEqualCanonicalizing($expected);
    }
});

test('PermissionSeeder is idempotent for permissions and role grants', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    $permissionCount = Permission::query()
        ->where('guard_name', 'web')
        ->whereIn('name', PermissionSeeder::PERMISSIONS)
        ->count();

    expect($permissionCount)->toBe(count(PermissionSeeder::PERMISSIONS))
        ->and(Role::findByName('admin')->permissions)->toHaveCount(count(PermissionSeeder::PERMISSIONS))
        ->and(Role::findByName('operator')->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(PermissionSeeder::ROLE_PERMISSIONS['operator']);
});
