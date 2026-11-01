<?php

// tests/Unit/Architecture/NotificationCategoryTest.php
//
// W40 — chip filter notifikasi menyaring per kelas. Kelas notifikasi baru yang
// lupa dipetakan akan diam-diam hilang dari chip "Review"/"Monev"; guard ini
// menolaknya lebih dulu.

use App\Enums\NotificationCategory;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

it('setiap kelas di app/Notifications punya kategori', function () {
    $tanpaKategori = [];

    foreach (Finder::create()->files()->in(app_path('Notifications'))->exclude('Concerns')->name('*.php')->sortByName() as $file) {
        $class = 'App\\Notifications\\'.$file->getBasename('.php');

        try {
            NotificationCategory::forClass($class);
        } catch (InvalidArgumentException) {
            $tanpaKategori[] = $class;
        }
    }

    expect($tanpaKategori)->toBe([]);
});

it('classes() kebalikan dari forClass()', function () {
    foreach (NotificationCategory::cases() as $category) {
        foreach ($category->classes() as $class) {
            expect(NotificationCategory::forClass($class))->toBe($category);
        }
    }
});
