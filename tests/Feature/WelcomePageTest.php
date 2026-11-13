<?php

// K7 (pemilik 2026-11-13) — `/` untuk tamu adalah halaman sambutan dengan pengumuman publik, bukan
// langsung login (membalik W157). User yang sudah masuk tetap ke Beranda.

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

it('[K7] tamu yang membuka / melihat halaman sambutan dengan pengumuman publik', function () {
    $public = Announcement::create([
        'title' => 'Pembukaan Hibah 2027',
        'content' => 'Isi pengumuman.',
        'type' => 'public',
        'created_by' => User::factory()->create()->id,
    ]);
    Announcement::create([
        'title' => 'Internal',
        'content' => 'Hanya untuk user masuk.',
        'type' => 'private',
        'created_by' => User::factory()->create()->id,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome/WelcomePage')
            ->where('canRegister', true)
            ->has('announcements', 1)
            ->where('announcements.0.id', $public->id)
            ->where('announcements.0.title', 'Pembukaan Hibah 2027'));
});

it('[K7] tamu yang baru keluar mendarat di halaman sambutan, bukan halaman ber-auth', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
    $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome/WelcomePage'));
});

it('user yang masuk membuka / dialihkan ke beranda', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});

it('halaman login tetap bisa dibuka langsung', function () {
    $this->get(route('login'))->assertOk();
});

it('layar Roles dan Permissions tidak lagi ada', function () {
    expect(Route::has('admin.roles.index'))->toBeFalse()
        ->and(Route::has('admin.permissions.index'))->toBeFalse();
});
