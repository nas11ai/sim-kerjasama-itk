<?php

use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

test('PermissionSeeder creates all fifteen canonical permissions', function () {
    $this->seed(PermissionSeeder::class);

    $names = Permission::query()
        ->where('guard_name', 'web')
        ->whereIn('name', PermissionSeeder::PERMISSIONS)
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($names)->toHaveCount(15)
        ->and($names)->toEqualCanonicalizing(PermissionSeeder::PERMISSIONS);
});

test('PermissionSeeder is idempotent', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    $count = Permission::query()
        ->where('guard_name', 'web')
        ->whereIn('name', PermissionSeeder::PERMISSIONS)
        ->count();

    expect($count)->toBe(15);
});
