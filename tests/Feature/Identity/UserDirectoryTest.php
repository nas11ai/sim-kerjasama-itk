<?php

// OPERATOR-08-01 — daftar user. Dijaga users.verify ∨ users.manage (W47); status akun dan
// status verifikasi adalah dua sumbu terpisah (W49), dan akun undangan tidak menunggu
// verifikasi (IAM-REG-14).

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function directoryViewer(string $permission = 'users.verify'): User
{
    $user = User::factory()->create(['is_active' => true, 'name' => 'Aaa Penonton']);
    Permission::findOrCreate($permission, 'web');
    $user->givePermissionTo($permission);

    return $user;
}

/** @return list<string> */
function directoryNames(User $viewer, array $query = []): array
{
    $names = [];
    test()->actingAs($viewer)->get(route('admin.users.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$names) {
            $names = array_column($page->toArray()['props']['users']['data'], 'name');
        });

    return array_values(array_diff($names, [$viewer->name]));
}

it('Operator pemegang users.verify membuka daftar user', function () {
    $this->actingAs(directoryViewer())->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Identity/UserIndexPage')
            ->has('users.data')
            ->has('filters')
            ->has('roleOptions')
            ->has('accountOptions')
            ->has('verificationOptions')
            ->has('awaitingVerification')
            ->where('can.verify', true)
            ->where('can.manageInvitations', true));
});

it('pemegang users.manage membuka daftar user', function () {
    $this->actingAs(directoryViewer('users.manage'))->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.verify', false));
});

it('peneliti mendapat 403', function () {
    $this->actingAs(directoryViewer('submissions.create'))->get(route('admin.users.index'))->assertForbidden();
});

it('filter account=inactive hanya memuat user nonaktif', function () {
    User::factory()->create(['name' => 'Budi Aktif', 'is_active' => true]);
    User::factory()->create(['name' => 'Joko Nonaktif', 'is_active' => false]);

    expect(directoryNames(directoryViewer(), ['account' => 'inactive']))->toBe(['Joko Nonaktif']);
});

it('filter verification=pending memuat akun pending dan tidak memuat akun undangan', function () {
    UserProfile::factory()->pending()->create(['user_id' => User::factory()->create(['name' => 'Maya Pending'])->id]);
    // IAM-REG-14 — akun undangan lahir aktif tanpa NIDN.
    UserProfile::factory()->create([
        'user_id' => User::factory()->create(['name' => 'Ina Undangan'])->id,
        'nidn' => null,
    ]);

    expect(directoryNames(directoryViewer(), ['verification' => 'pending']))->toBe(['Maya Pending']);
});

it('filter role=reviewer_internal', function () {
    Role::findOrCreate('reviewer_internal', 'web');
    User::factory()->create(['name' => 'Rina Penilai'])->assignRole('reviewer_internal');
    User::factory()->create(['name' => 'Budi Biasa']);

    expect(directoryNames(directoryViewer(), ['role' => 'reviewer_internal']))->toBe(['Rina Penilai']);
});

it('pencarian NIDN sebagian dan karakter wildcard di-escape', function () {
    UserProfile::factory()->create(['user_id' => User::factory()->create(['name' => 'Rina Wijaya'])->id, 'nidn' => '0987654321']);
    UserProfile::factory()->create(['user_id' => User::factory()->create(['name' => 'Budi Santoso'])->id, 'nidn' => '0123456789']);
    $viewer = directoryViewer();

    expect(directoryNames($viewer, ['search' => '0987']))->toBe(['Rina Wijaya'])
        ->and(directoryNames($viewer, ['search' => '%']))->toBe([])
        ->and(directoryNames($viewer, ['search' => '_']))->toBe([]);
});

it('nilai filter tak dikenal diabaikan', function () {
    User::factory()->create(['name' => 'Budi Santoso']);

    expect(directoryNames(directoryViewer(), ['role' => 'dukun', 'account' => 'x', 'verification' => 'y']))->toBe(['Budi Santoso']);
});

it('status verifikasi null untuk akun terverifikasi dan akun tanpa profil', function () {
    $this->freezeSecond();
    UserProfile::factory()->create(['user_id' => User::factory()->create(['name' => 'Budi Terverifikasi'])->id]);
    User::factory()->create(['name' => 'Cici Tanpa Profil']);
    UserProfile::factory()->rejected()->create(['user_id' => User::factory()->create(['name' => 'Dodi Ditolak'])->id]);

    $this->actingAs(directoryViewer())->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.1.name', 'Budi Terverifikasi')
            ->where('users.data.1.status', ['active' => true, 'verification' => null, 'online' => false, 'lastActiveAt' => null, 'checkedAt' => now()->toIso8601String()])
            ->where('users.data.2.status.verification', null)
            ->where('users.data.3.status.verification', ['value' => 'rejected', 'label' => 'Ditolak'])
            ->where('users.data.1.showUrl', route('admin.users.show', User::query()->where('name', 'Budi Terverifikasi')->value('id'))));
});

it('route admin.users.create dan admin.users.store tidak ada lagi', function () {
    expect(Route::has('admin.users.create'))->toBeFalse()
        ->and(Route::has('admin.users.store'))->toBeFalse();
});
