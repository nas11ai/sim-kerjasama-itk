<?php

// BR-IAM-04 — layar Menunggu Verifikasi (SHARED-01-05): verifikasi NIDN menggerbang
// submit, bukan login, jadi akun pending dan ditolak tetap masuk dan mendarat di sini.

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Permission;

/** @param  callable(Factory<UserProfile>): Factory<UserProfile>  $state */
function accountWithProfile(callable $state): User
{
    $user = User::factory()->create(['email' => 'budi@itk.ac.id']);
    $state(UserProfile::factory())->create(['user_id' => $user->id, 'nidn' => '0011223344']);

    return $user;
}

it('[IAM-VERIFY-01] user pending yang login melihat layar menunggu verifikasi', function () {
    $user = accountWithProfile(fn ($factory) => $factory->pending());

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('verification.status'));

    $this->get(route('verification.status'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/VerificationStatusPage')
            ->where('verification', [
                'status' => 'pending',
                'statusLabel' => 'Menunggu verifikasi',
                'nidn' => '0011223344',
                'email' => 'budi@itk.ac.id',
                'rejectionReason' => null,
                'rejectedAt' => null,
            ]));
});

it('[IAM-VERIFY-07] alasan penolakan terbaca di layar verifikasi', function () {
    $user = accountWithProfile(fn ($factory) => $factory->rejected('NIDN tidak ditemukan di PDDikti.'));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('verification.status'));

    $this->get(route('verification.status'))
        ->assertInertia(fn ($page) => $page
            ->where('verification.status', 'rejected')
            ->where('verification.rejectionReason', 'NIDN tidak ditemukan di PDDikti.'));
});

it('mengalihkan akun terverifikasi ke dashboard', function () {
    $user = accountWithProfile(fn ($factory) => $factory);

    $this->actingAs($user)->get(route('verification.status'))
        ->assertRedirect(route('dashboard'));
});

it('mengalihkan akun tanpa profil ke dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('verification.status'))
        ->assertRedirect(route('dashboard'));
});

it('user pending dengan deep link tetap diantar ke tujuannya', function () {
    $user = accountWithProfile(fn ($factory) => $factory->pending());

    $this->get(route('profile.edit'))->assertRedirect(route('login'));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('profile.edit'));
});

// W63 — flow diagram UX: akun aktif berprofil belum lengkap → Lengkapi Profil (BR-IAM-03).
it('akun terverifikasi berprofil belum lengkap mendarat di Lengkapi Profil', function () {
    $user = accountWithProfile(fn ($factory) => $factory->incomplete());
    $user->givePermissionTo(Permission::findOrCreate('submissions.create', 'web'));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('profile.complete'));
});

it('[IAM-VERIFY-01] akun pending berprofil belum lengkap tetap mendarat di layar verifikasi', function () {
    $user = accountWithProfile(fn ($factory) => $factory->pending()->incomplete());
    $user->givePermissionTo(Permission::findOrCreate('submissions.create', 'web'));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('verification.status'));
});

it('pengelola tanpa profil tetap mendarat di dasbor', function () {
    $user = User::factory()->create(['email' => 'admin@itk.ac.id']);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'), Permission::findOrCreate('submissions.create', 'web'));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

it('tamu dialihkan ke login', function () {
    $this->get(route('verification.status'))->assertRedirect(route('login'));
});
