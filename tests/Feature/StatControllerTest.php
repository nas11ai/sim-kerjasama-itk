<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $permission = Permission::findOrCreate('users.manage');
    $admin = Role::findOrCreate('Admin');
    $admin->givePermissionTo($permission);
});

test('admin can access reviewer stats endpoint without SQL error', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('Fungsi EXTRACT(YEAR) standar SQL tidak didukung di SQLite in-memory testing.');
    }

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this
        ->actingAs($user)
        ->get('/admin/stats/get-reviewers');

    $response->assertOk();
});

test('admin can access form submissions stats endpoint without SQL error', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this
        ->actingAs($user)
        ->get('/admin/stats/get-form-submissions');

    $response->assertOk();
});
