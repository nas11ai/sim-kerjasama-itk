<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)->assertInertia(fn ($page) => $page
        ->component('Auth/LoginPage')
        ->where('canResetPassword', true)
        ->where('status', null));
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('user.dashboard', absolute: false));
});

// Percabangan tujuan setelah login tidak pernah teruji karena controller-nya
// memotong jalur ini saat `app()->environment('testing')`. Cabang itu dicabut;
// dua test berikut menempuh percabangan yang sesungguhnya.

test('operator pemegang users.manage mendarat di dasbor admin', function () {
    Permission::findOrCreate('users.manage', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $this->post('/login', [
        'email' => $operator->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));
});

test('peneliti biasa mendarat di dasbornya sendiri', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('user.dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
