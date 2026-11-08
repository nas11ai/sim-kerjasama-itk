<?php

// tests/Feature/Inertia/ErrorPageTest.php
//
// fe-v2-design W10/W163 — 403/404 (dan 500/503 saat debug mati) tampil sebagai halaman
// Inertia `Error/ErrorPage` di shell v2, bukan halaman bawaan Laravel.

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Exceptions\UnauthorizedException;

it('[SM-ACCESS-03] penolakan akses tampil di halaman error v2', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user.submissions.show', FormSubmission::factory()->create()))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/ErrorPage')
            ->where('status', 403));
});

it('URL tak dikenal tanpa sesi tampil sebagai 404 v2', function () {
    $this->get('/tidak-ada-sama-sekali')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/ErrorPage')
            ->where('status', 404)
            ->where('message', null)
            ->where('auth.user', null));
});

it('bookmark legacy milik user yang masuk tampil 404 dengan shell aplikasi', function (string $url) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get($url)
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/ErrorPage')
            ->where('status', 404)
            ->where('auth.user.id', $user->id));
})->with(['/user/dashboard', '/reviewer/submissions', '/admin/roles']);

it('kunjungan Inertia yang ditolak menerima objek halaman error', function () {
    $this->actingAs(User::factory()->create())
        ->withHeaders([
            'X-Inertia' => 'true',
            // Sama dengan middleware: ada hash manifest bila aset sudah di-build.
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('user.submissions.show', FormSubmission::factory()->create()))
        ->assertForbidden()
        ->assertJsonPath('component', 'Error/ErrorPage')
        ->assertJsonPath('props.status', 403);
});

it('pesan 403 tulisan manusia diteruskan, bawaan framework tidak', function (Closure $throw, ?string $expected) {
    Route::middleware('web')->get('/_tolak', $throw);

    $this->actingAs(User::factory()->create())
        ->get('/_tolak')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/ErrorPage')
            ->where('message', $expected));
})->with([
    'abort bermesan' => [fn () => abort(403, 'Anda tidak memiliki akses ke formulir ini.'), 'Anda tidak memiliki akses ke formulir ini.'],
    'Action bermesan' => [fn () => throw new AuthorizationException('Pengajuan ini sudah terkunci.'), 'Pengajuan ini sudah terkunci.'],
    'abort tanpa pesan' => [fn () => abort(403), null],
    'Policy bawaan' => [fn () => throw new AuthorizationException, null],
    'middleware permission' => [fn () => throw UnauthorizedException::forPermissions(['users.manage']), null],
]);

it('500 dirender halaman error tanpa membocorkan pesan bila debug mati', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_meledak', fn () => throw new RuntimeException('rahasia'));

    $this->get('/_meledak')
        ->assertStatus(500)
        ->assertDontSee('rahasia')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/ErrorPage')
            ->where('status', 500)
            ->where('message', null));
});

it('500 tetap halaman debug bila debug menyala', function () {
    config(['app.debug' => true]);
    Route::middleware('web')->get('/_meledak', fn () => throw new RuntimeException('rahasia'));

    $response = $this->get('/_meledak')->assertStatus(500)->assertSee('rahasia');

    // Halaman Inertia penuh adalah View berdata `page`; halaman debug bukan.
    expect($response->baseResponse->original)->not->toBeInstanceOf(View::class);
});

it('503 pemeliharaan dirender halaman error bila debug mati', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_rawat', fn () => abort(503));

    $this->get('/_rawat')
        ->assertStatus(503)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/ErrorPage')
            ->where('status', 503));
});

it('permintaan JSON tetap menerima JSON', function () {
    $this->getJson('/tidak-ada-sama-sekali')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('419 kembali ke halaman sebelumnya dengan toast sesi kedaluwarsa', function () {
    Route::middleware('web')->post('/_kedaluwarsa', fn () => throw new TokenMismatchException);

    $this->actingAs(User::factory()->create())
        ->from(route('profile.edit'))
        ->post('/_kedaluwarsa')
        ->assertStatus(303)
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('error', 'Sesi kedaluwarsa. Silakan ulangi.');
});
