<?php

// W4 — label manusia untuk setiap permission dan peran DDD; satu katalog dipakai
// detail user, undangan, dan registrasi undangan, sehingga tak ada nama mentah di layar.

use App\Support\Announcement\AnnouncementAudience;
use App\Support\Identity\PermissionCatalog;
use App\Support\Identity\RoleCatalog;
use Database\Seeders\PermissionSeeder;

it('setiap permission katalog punya label', function () {
    $labelled = array_keys(PermissionCatalog::LABELS);
    sort($labelled);
    $seeded = PermissionSeeder::PERMISSIONS;
    sort($seeded);

    expect($labelled)->toBe($seeded);
});

it('setiap permission katalog punya penjelasan tooltip', function () {
    expect(array_keys(PermissionCatalog::DESCRIPTIONS))->toBe(array_keys(PermissionCatalog::LABELS))
        ->and(array_filter(PermissionCatalog::DESCRIPTIONS, static fn (string $text): bool => trim($text) === ''))->toBe([]);
});

it('setiap peran DDD punya label', function () {
    expect(array_diff(array_keys(PermissionSeeder::ROLE_PERMISSIONS), array_keys(RoleCatalog::LABELS)))->toBe([]);
});

it('ASSIGNABLE tidak memuat peran reviewer', function () {
    expect(RoleCatalog::ASSIGNABLE)->toBe(['researcher', 'operator', 'admin'])
        ->and(RoleCatalog::ASSIGNABLE)->not->toContain('reviewer_internal')
        ->and(RoleCatalog::ASSIGNABLE)->not->toContain('reviewer_external');
});

it('sasaran pengumuman memakai himpunan peran katalog', function () {
    expect(array_keys(AnnouncementAudience::ROLE_LABELS))->toBe(array_keys(RoleCatalog::LABELS));
});

it('label dan options jatuh ke nama mentah untuk nama tak dikenal', function () {
    expect(RoleCatalog::label('Tenaga Kependidikan'))->toBe('Tenaga Kependidikan')
        ->and(RoleCatalog::label('operator'))->toBe('Operator')
        ->and(PermissionCatalog::label('legacy.thing'))->toBe('legacy.thing')
        ->and(PermissionCatalog::options(['users.verify']))->toBe([[
            'value' => 'users.verify',
            'label' => 'Memverifikasi NIDN',
            'description' => 'Memverifikasi atau menolak NIDN pendaftar baru.',
        ]])
        ->and(RoleCatalog::options(['researcher']))->toBe([['value' => 'researcher', 'label' => 'Peneliti']]);
});
