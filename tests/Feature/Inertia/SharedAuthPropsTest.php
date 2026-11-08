<?php

// tests/Feature/Inertia/SharedAuthPropsTest.php
//
// fe-v2-design §4.4, W162 — `roles` dan `is_reviewer` dihapus dari shared props setelah
// legacy AppSidebar (satu-satunya pembacanya) hilang. Sidebar v2 membaca `permissions`.

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

it('auth.user hanya memuat id, name, email, dan permissions', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('auth.user', fn (Assert $user) => $user
            ->hasAll(['id', 'name', 'email', 'permissions'])
            ->missing('roles')
            ->missing('is_reviewer')));
});

it('prop flash bersama hanya membawa peringatan beban reviewer', function () {
    // W162 — pesan sukses/galat sampai lewat flash native Inertia (bridgeSessionFlash → toast).
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('flash', fn (Assert $flash) => $flash
            ->has('workloadWarnings')
            ->missing('message')
            ->missing('error')
            ->missing('success')));
});

it('kolom users.is_reviewer yang tak pernah ditulis sudah dibuang', function () {
    // W162 — "reviewer" = sedang dalam masa tugas (BR-REV-09, ReviewersWithinTenure), bukan flag.
    expect(Schema::hasColumn('users', 'is_reviewer'))->toBeFalse();
});

it('tamu menerima auth.user null', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});
