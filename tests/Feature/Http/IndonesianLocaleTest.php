<?php

// Paket 2 P1 — locale bocor: .env bawaan (APP_LOCALE=en, APP_NAME=Laravel) membuat <html lang="en">,
// judul "- Laravel", dan pesan login/validasi berbahasa Inggris. UI SIMPAS hanya berbahasa Indonesia.

use App\Models\User;

it('halaman dirender dengan lang id dan judul SIMPAS', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    expect($html)->toContain('<html lang="id">')
        ->and($html)->toContain('<title inertia>SIMPAS</title>');
});

it('login gagal dijelaskan dalam bahasa Indonesia', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'salah-sekali'])
        ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak cocok dengan akun mana pun.']);
});

it('pesan validasi bawaan berbahasa Indonesia dengan nama field terjemahan', function () {
    $this->post('/login', ['email' => '', 'password' => ''])
        ->assertSessionHasErrors([
            'email' => 'Email wajib diisi.',
            'password' => 'Kata sandi wajib diisi.',
        ]);
});

it('lang/id memuat setiap kunci terjemahan bawaan Laravel', function (string $group) {
    $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($lines as $key => $line) {
            $keys = [...$keys, ...(is_array($line) ? $flatten($line, "{$prefix}{$key}.") : ["{$prefix}{$key}"])];
        }

        return $keys;
    };
    $bawaan = require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$group}.php");
    $terjemahan = require lang_path("id/{$group}.php");

    // custom/attributes milik aplikasi, bukan bagian dari daftar bawaan.
    $hilang = array_diff(
        array_filter($flatten($bawaan), fn (string $key) => !str_starts_with($key, 'custom.') && !str_starts_with($key, 'attributes')),
        $flatten($terjemahan),
    );

    expect(array_values($hilang))->toBe([]);
})->with(['auth', 'passwords', 'pagination', 'validation']);
