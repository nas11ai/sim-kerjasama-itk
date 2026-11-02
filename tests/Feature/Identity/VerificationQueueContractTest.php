<?php

// OPERATOR-08-02 — kontrak antrian verifikasi NIDN (W53: "Ditolak pada" dari jejak audit).

use App\Actions\Identity\RejectNidnVerification;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function queueVerifier(): User
{
    Permission::findOrCreate('users.verify', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.verify');

    return $operator;
}

function queueProfile(string $name, string $nidn, string $state = 'pending'): UserProfile
{
    $user = User::factory()->create(['name' => $name]);

    return UserProfile::factory()->{$state}()->create(['user_id' => $user->id, 'nidn' => $nidn]);
}

it('kontrak kartu pending', function () {
    $profile = queueProfile('Dr. Maya Sari', '0654321098');

    $this->actingAs(queueVerifier())
        ->get(route('admin.verifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Identity/VerificationIndexPage')
            ->has('pending', 1)
            ->has('pending.0', fn (AssertableInertia $card) => $card
                ->where('profileId', $profile->id)
                ->where('name', 'Dr. Maya Sari')
                ->where('nidn', '0654321098')
                ->where('rejectedAt', null)
                ->where('rejectionReason', null)
                ->missing('files')
                ->hasAll(['email', 'organization', 'registeredAt']))
            ->has('rejected', 0));
});

it('rejectedAt dari entri audit penolakan terbaru', function () {
    Notification::fake();
    $profile = queueProfile('Dr. Hasan Basri', '0543210987');

    $this->travelTo(now()->subDays(5));
    RejectNidnVerification::run($profile, 'Penolakan pertama.');
    $this->travelBack();
    $this->travelTo(now()->subDay()->startOfDay()->addHours(9));
    RejectNidnVerification::run($profile->fresh(), 'Penolakan kedua.');
    $latest = now()->toIso8601String();
    $this->travelBack();

    $this->actingAs(queueVerifier())
        ->get(route('admin.verifications.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pending', 0)
            ->has('rejected', 1)
            ->where('rejected.0.profileId', $profile->id)
            ->where('rejected.0.rejectedAt', $latest)
            ->where('rejected.0.rejectionReason', 'Penolakan kedua.')
            ->etc());
});

it('[IAM-REG-14] akun undangan tidak masuk pending maupun rejected', function () {
    // Akun undangan lahir `active` tanpa NIDN (RegisterByInvitation).
    UserProfile::factory()->create(['user_id' => User::factory()->create()->id, 'nidn' => null]);
    queueProfile('Dr. Maya Sari', '0654321098');

    $this->actingAs(queueVerifier())
        ->get(route('admin.verifications.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pending', 1)
            ->has('rejected', 0)
            ->etc());
});

it('pemegang users.manage tanpa users.verify 403', function () {
    Permission::findOrCreate('users.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('users.manage');
    $profile = queueProfile('Dr. Maya Sari', '0654321098');

    $this->actingAs($admin)->get(route('admin.verifications.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.verifications.verify', $profile))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.verifications.nidn', $profile), ['nidn' => '1'])->assertForbidden();
});

it('akun ditolak membaca rejectedAt di layar status', function () {
    Notification::fake();
    $profile = queueProfile('Dr. Hasan Basri', '0543210987');
    RejectNidnVerification::run($profile, 'NIDN tidak cocok.');

    $this->actingAs($profile->user)
        ->get(route('verification.status'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('verification.status', 'rejected')
            ->where('verification.rejectedAt', fn (?string $value) => $value !== null));
});

// W61 (pemilik, 2026-11-02) — aplikasi tidak menyimpan KTP; pembaca berkas biodata W58 dihapus.
it('kartu verifikasi tidak membawa berkas identitas', function () {
    queueProfile('Dr. Maya Sari', '0654321098');

    $this->actingAs(queueVerifier())
        ->get(route('admin.verifications.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('pending.0.files'));
});

it('route berkas biodata tidak ada lagi', function () {
    expect(Route::has('admin.verifications.files.show'))->toBeFalse();
});
