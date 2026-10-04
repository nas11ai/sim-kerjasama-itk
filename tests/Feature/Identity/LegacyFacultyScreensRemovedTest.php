<?php

// tests/Feature/Identity/LegacyFacultyScreensRemovedTest.php
//
// Layar Fakultas dan Prodi lama menyunting tabel `faculties`/`study_programs`
// yang tak dibaca siapa pun lagi; struktur organisasi hidup di pohon Organization.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan layar Fakultas dan Prodi lama', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.faculties.index',
    'admin.faculties.create',
    'admin.faculties.store',
    'admin.faculties.show',
    'admin.faculties.edit',
    'admin.faculties.update',
    'admin.faculties.destroy',
    'admin.faculties.study-programs',
    'admin.faculties.study-programs.create',
    'admin.faculties.study-programs.store',
    'admin.faculties.study-programs.edit',
    'admin.faculties.study-programs.update',
    'admin.faculties.study-programs.destroy',
]);
