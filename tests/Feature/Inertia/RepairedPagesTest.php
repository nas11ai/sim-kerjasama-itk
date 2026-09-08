<?php

// tests/Feature/Inertia/RepairedPagesTest.php
//
// Tujuh rute ini merender nama halaman yang tidak menunjuk berkas Vue mana
// pun: lima menyimpang setelah repo mengganti pola nama menjadi `*Page.vue`,
// dua halamannya memang tidak pernah dibuat. Semuanya lolos suite lama karena
// setiap `assertInertia(...)` mematikan pemeriksaan keberadaan halaman —
// pemeriksaan itu memang tidak bisa jalan tanpa `config/inertia.php`, yang
// sekarang ada.

use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function adminUser(): User
{
    Permission::findOrCreate('users.manage', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('users.manage');

    return $admin;
}

it('membuka detail fakultas beserta program studinya', function () {
    $faculty = Faculty::create(['name' => 'Fakultas Sains dan Teknologi Informasi']);
    StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Informatika']);
    StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Matematika']);

    $this->actingAs(adminUser())
        ->get(route('admin.faculties.show', $faculty->id))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Faculties/ShowPage')
                ->where('faculty.name', 'Fakultas Sains dan Teknologi Informasi')
                ->has('faculty.study_programs', 2)
        );
});

it('membuka fakultas yang belum punya program studi', function () {
    $faculty = Faculty::create(['name' => 'Fakultas Teknologi Industri']);

    $this->actingAs(adminUser())
        ->get(route('admin.faculties.show', $faculty->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('faculty.study_programs', 0));
});

it('membuka halaman-halaman yang namanya sempat menyimpang', function (string $routeName, string $component) {
    $this->actingAs(adminUser())
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['admin.permissions.index', 'Permissions/IndexPage'],
    ['admin.roles.create', 'Roles/CreatePage'],
    ['admin.users.create', 'Users/CreatePage'],
]);
