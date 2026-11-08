<?php

// SHARED-01-07 Profil Saya — sengaja tanpa skenario BDD (bdd/README.md), tapi BR
// yang menyentuhnya tetap ditegakkan: email/NIDN/organisasi tak bisa diubah sendiri
// (BR-IAM-01/02/18), tanpa notifikasi (BR-IAM-15), diaudit (BR-RPT-09).

use App\Enums\VerificationStatus;
use App\Models\Form;
use App\Models\FormType;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function profileOwner(array $profile = []): User
{
    $user = User::factory()->create(['name' => 'Budi Santoso']);
    UserProfile::factory()->create(['user_id' => $user->id, 'phone' => '0812-1111-2222', 'expertise' => 'Konversi Energi', ...$profile]);

    return $user->fresh();
}

it('Profil Saya merender kontrak OwnProfileData', function () {
    $user = profileOwner();

    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
        ->component('Profile/ShowPage')
        ->where('profile.email', $user->email)
        ->where('profile.hasProfile', true)
        ->where('profile.phone', '0812-1111-2222')
        ->has('profile.missing'));
});

it('memperbarui nama, telepon, dan keahlian beserta auditnya', function () {
    $user = profileOwner();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Budi S.', 'phone' => '0812-1111-2222', 'expertise' => 'Energi Terbarukan'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user->refresh();
    $entry = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->where('description', 'Profil diperbarui')->sole();

    expect($user->name)->toBe('Budi S.')
        ->and($user->userProfile->expertise)->toBe('Energi Terbarukan')
        ->and($entry->properties->all())->toBe([
            'old' => ['name' => 'Budi Santoso', 'expertise' => 'Konversi Energi'],
            'new' => ['name' => 'Budi S.', 'expertise' => 'Energi Terbarukan'],
        ]);
});

it('tanpa perubahan tidak ada entri audit', function () {
    $user = profileOwner();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Budi Santoso', 'phone' => '0812-1111-2222', 'expertise' => 'Konversi Energi']);

    expect(Activity::query()->where('description', 'Profil diperbarui')->count())->toBe(0);
});

it('field di luar formulir diabaikan', function () {
    $user = profileOwner(['nidn' => '0011223344', 'verification_status' => VerificationStatus::Pending->value]);
    $profile = $user->userProfile;
    // actingAs berbagi instance yang sama dengan controller: simpan nilai aslinya dulu.
    $email = $user->email;
    $organizationId = $profile->organization_id;

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Budi', 'phone' => '0812-1111-2222', 'expertise' => 'X',
        'email' => 'lain@example.com', 'nidn' => '9999999999',
        'organization_id' => 999, 'verification_status' => 'active',
    ])->assertSessionHasNoErrors();

    $fresh = $profile->fresh();

    expect($user->fresh()->email)->toBe($email)
        ->and($fresh->nidn)->toBe('0011223344')
        ->and($fresh->organization_id)->toBe($organizationId)
        ->and($fresh->verification_status)->toBe(VerificationStatus::Pending);
});

it('telepon tidak sah ditolak', function () {
    $this->actingAs(profileOwner())
        ->patch(route('profile.update'), ['name' => 'Budi', 'phone' => 'abc', 'expertise' => 'X'])
        ->assertSessionHasErrors('phone');
});

it('telepon dan keahlian wajib bagi pemilik profil', function () {
    $this->actingAs(profileOwner())
        ->patch(route('profile.update'), ['name' => 'Budi', 'phone' => '', 'expertise' => ''])
        ->assertSessionHasErrors(['phone', 'expertise']);
});

it('user tanpa profil hanya mengubah nama', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Admin Baru'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Admin Baru')
        ->and(UserProfile::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('menyunting profil sendiri tidak mengirim notifikasi', function () {
    Notification::fake();

    $this->actingAs(profileOwner())->patch(route('profile.update'), ['name' => 'Budi', 'phone' => '0812-1111-2222', 'expertise' => 'X']);

    Notification::assertNothingSent();
});

it('akun nonaktif tidak bisa menyunting profil', function () {
    $user = profileOwner();
    $user->forceFill(['is_active' => false])->save();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Nama Lain', 'phone' => '0812-1111-2222', 'expertise' => 'X'])
        ->assertRedirect(route('login'));

    expect($user->fresh()->name)->toBe('Budi Santoso');
});

// W61 — biodata Form-Engine dihapus; route lamanya ikut hilang di F99 (LegacyRoutesGoneTest).
it('pengguna role Mahasiswa tanpa biodata tidak dialihkan', function () {
    // Tiga gerbang DDD (verifikasi, keaktifan, kelengkapan profil), bukan empat.
    $type = FormType::forceCreate(['id' => FormType::BIODATA_ID, 'name' => 'Biodata']);
    Form::factory()->create(['form_type_id' => $type->id, 'is_active' => true]);
    $user = profileOwner();
    $user->assignRole(Role::findOrCreate('Mahasiswa', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('submissions.view-own', 'web'));

    $this->actingAs($user)->get(route('user.submissions.index'))->assertOk();
});
