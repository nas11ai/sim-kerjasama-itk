<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DummyUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

test('DatabaseSeeder includes PermissionSeeder before DummyUser and keeps OrganizationSeeder', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseSeeder::class))->getFileName());

    expect($source)->toContain('PermissionSeeder::class')
        ->and($source)->toContain('OrganizationSeeder::class')
        ->and(strpos($source, 'PermissionSeeder::class'))->toBeLessThan(strpos($source, 'DummyUserSeeder::class'));
});

test('Role Permission and DummyUser seeders are idempotent when re-run together', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(DummyUserSeeder::class);

    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(DummyUserSeeder::class);

    expect(Permission::query()->where('guard_name', PermissionSeeder::GUARD)->whereIn('name', PermissionSeeder::PERMISSIONS)->count())
        ->toBe(count(PermissionSeeder::PERMISSIONS))
        ->and(Role::findByName('admin', PermissionSeeder::GUARD)->permissions)->toHaveCount(count(PermissionSeeder::PERMISSIONS))
        ->and(Role::findByName('Admin', PermissionSeeder::GUARD)->permissions)->toHaveCount(count(PermissionSeeder::PERMISSIONS));
});
