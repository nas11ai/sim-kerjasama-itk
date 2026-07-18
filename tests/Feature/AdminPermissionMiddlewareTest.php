<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

test('admin routes reject users without users.manage permission', function () {
    Permission::findOrCreate('users.manage');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/forms')
        ->assertForbidden();
});

test('admin routes allow users with users.manage permission', function () {
    Permission::findOrCreate('users.manage');

    $user = User::factory()->create();
    $user->givePermissionTo('users.manage');

    $this->actingAs($user)
        ->get('/forms')
        ->assertRedirect(route('admin.forms.index'));
});

test('admin routes allow Super Admin via Gate before', function () {
    Role::findOrCreate('Super Admin');

    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user)
        ->get('/forms')
        ->assertRedirect(route('admin.forms.index'));
});

test('routes web file has no role middleware aliases', function () {
    $routes = file_get_contents(base_path('routes/web.php'));

    expect($routes)->not->toMatch("/['\"]role:/");
});
