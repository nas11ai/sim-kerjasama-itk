<?php

// tests/Feature/Inertia/RepairedPagesTest.php
//
// Rute-rute ini merender nama halaman yang tidak menunjuk berkas Vue mana
// pun: menyimpang setelah repo mengganti pola nama menjadi `*Page.vue`
// (detail fakultas ikut dicabut bersama layar Fakultas lama). Semuanya lolos
// suite lama karena setiap `assertInertia(...)` mematikan pemeriksaan
// keberadaan halaman — pemeriksaan itu memang tidak bisa jalan tanpa
// `config/inertia.php`, yang sekarang ada.

use App\Models\User;
use Spatie\Permission\Models\Permission;

function adminUser(): User
{
    Permission::findOrCreate('users.manage', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('users.manage');

    return $admin;
}

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
