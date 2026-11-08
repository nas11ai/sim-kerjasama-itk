<?php

// tests/Feature/HomeRedirectTest.php
//
// fe-v2-design W157 — `/` tidak lagi merender WelcomePage (K3); layar Roles/Permissions dibuang.

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('tamu yang membuka / dialihkan ke login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('user yang masuk membuka / dialihkan ke dasbor', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});

it('layar Roles dan Permissions tidak lagi ada', function () {
    expect(Route::has('admin.roles.index'))->toBeFalse()
        ->and(Route::has('admin.permissions.index'))->toBeFalse()
        ->and(Route::has('welcome'))->toBeFalse();
});
