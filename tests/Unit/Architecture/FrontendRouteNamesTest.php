<?php

// tests/Unit/Architecture/FrontendRouteNamesTest.php
//
// Nama route di Vue diselesaikan Ziggy saat tombolnya diklik, bukan saat
// build: nama yang tidak terdaftar lolos vue-tsc, lolos test, dan baru
// melempar di tangan pengguna. Test ini memindahkan kegagalan itu ke sini.

use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Nama yang sudah rusak saat penjaga ini dipasang. Daftar ini hanya boleh
 * menyusut: hapus barisnya begitu tautannya dibetulkan atau halamannya
 * diganti layar SIMPAS v2. Jangan menambah nama baru ke sini.
 */
const KNOWN_BROKEN_FRONTEND_ROUTES = [];

/** @return array<string, list<string>> nama route => berkas yang memakainya */
function frontendRouteNames(): array
{
    $names = [];
    $files = Finder::create()->files()->in(resource_path('js'))
        ->name(['*.vue', '*.ts'])->notName('ziggy.js')->sortByName();

    foreach ($files as $file) {
        // route('nama') di template/script, dan routeName: 'nama' di konfigurasi navigasi
        preg_match_all("/\b(?:route\(\s*|routeName:\s*)'([^']+)'/", $file->getContents(), $found);

        foreach ($found[1] as $name) {
            $names[$name][] = $file->getRelativePathname();
        }
    }

    return $names;
}

it('setiap nama route yang dipakai frontend terdaftar di Laravel', function () {
    $unregistered = [];

    foreach (frontendRouteNames() as $name => $files) {
        if (!Route::has($name) && !in_array($name, KNOWN_BROKEN_FRONTEND_ROUTES, true)) {
            $unregistered[] = $name.' — '.implode(', ', array_unique($files));
        }
    }

    expect($unregistered)->toBe([], "Route tidak terdaftar, akan melempar saat diklik:\n".implode("\n", $unregistered));
});

it('daftar route rusak hanya memuat nama yang memang masih rusak dan masih dipakai', function () {
    $used = frontendRouteNames();

    $stale = array_values(array_filter(
        KNOWN_BROKEN_FRONTEND_ROUTES,
        static fn (string $name): bool => Route::has($name) || !array_key_exists($name, $used),
    ));

    expect($stale)->toBe([], 'Sudah dibetulkan atau tak dipakai lagi, hapus dari daftar: '.implode(', ', $stale));
});
