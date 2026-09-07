<?php

// tests/Feature/Identity/RegistrationPathTest.php
//
// BR-IAM-01, BR-IAM-02, BR-IAM-06, BR-IAM-12 — dua jalur akun lahir.
//
// Yang membedakan keduanya bukan cuma pintunya melainkan **empat bekal** yang
// dibawa akun barunya: role, organisasi, permission, dan status awal. Register
// #29 mengoreksi BDD yang semula menulis akun undangan lahir `pending`;
// verifikasi memverifikasi NIDN, dan mitra eksternal tidak punya NIDN.

use App\Actions\Identity\DeleteOrganization;
use App\Actions\Identity\RegisterByInvitation;
use App\Actions\Identity\RegisterResearcher;
use App\Enums\VerificationStatus;
use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function identityOrg(bool $active = true): Organization
{
    return Organization::create([
        'name' => 'Unit '.fake()->unique()->numerify('####'),
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => $active,
    ]);
}

/** @param array<string, mixed> $override */
function selfRegisterData(Organization $org, array $override = []): array
{
    Role::findOrCreate('researcher', 'web');

    return array_merge([
        'name' => 'Budi Santoso',
        'email' => 'budi'.fake()->unique()->numerify('###').'@itk.ac.id',
        'password' => 'rahasia-panjang',
        'nidn' => fake()->unique()->numerify('##########'),
        'organization_id' => $org->id,
    ], $override);
}

function invitationToken(Organization $org, array $override = []): InvitationToken
{
    return InvitationToken::create(array_merge([
        'token' => fake()->unique()->lexify('????????????????'),
        'organization_id' => $org->id,
        'permissions' => ['submissions.create'],
        'max_uses' => 5,
        'used_count' => 0,
        'expires_at' => now()->addMonth(),
        'created_by' => User::factory()->create()->id,
    ], $override));
}

it('[IAM-REG-01] dosen ITK mendaftar mandiri dan berstatus pending', function () {
    $user = RegisterResearcher::run(selfRegisterData(identityOrg()));

    expect($user->userProfile->verification_status)->toBe(VerificationStatus::Pending);
});

it('[IAM-REG-02] email di luar domain ITK ditolak pada jalur mandiri', function () {
    $org = identityOrg();

    expect(fn () => RegisterResearcher::run(selfRegisterData($org, ['email' => 'budi@gmail.com'])))
        ->toThrow(ValidationException::class)
        ->and(User::where('email', 'budi@gmail.com')->exists())->toBeFalse();
});

it('[IAM-REG-03] jalur undangan tidak dibatasi domain', function () {
    $token = invitationToken(identityOrg());

    $user = RegisterByInvitation::run($token, [
        'name' => 'Rina Kusuma',
        'email' => 'rina@perusahaan.co.id',
        'password' => 'rahasia-panjang',
    ]);

    expect($user->userProfile->verification_status)->toBe(VerificationStatus::Active);
});

it('[IAM-REG-04] NIDN yang sudah dipakai ditolak', function () {
    $org = identityOrg();
    RegisterResearcher::run(selfRegisterData($org, ['nidn' => '0011223344']));

    expect(fn () => RegisterResearcher::run(selfRegisterData($org, ['nidn' => '0011223344'])))
        ->toThrow(ValidationException::class)
        ->and(UserProfile::where('nidn', '0011223344')->count())->toBe(1);
});

it('[IAM-REG-05] NIDN unik masuk antrian verifikasi', function () {
    $user = RegisterResearcher::run(selfRegisterData(identityOrg(), ['nidn' => '0055667788']));

    expect(UserProfile::awaitingVerification()->pluck('user_id')->all())->toContain($user->id);
});

it('[IAM-REG-06] token yang habis kuotanya ditolak', function () {
    $token = invitationToken(identityOrg(), ['max_uses' => 3, 'used_count' => 3]);

    expect(fn () => RegisterByInvitation::run($token, [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]))->toThrow(ValidationException::class);
});

it('[IAM-REG-07] token kedaluwarsa ditolak', function () {
    $this->travelTo('2026-03-10 09:00:00');
    $token = invitationToken(identityOrg(), ['expires_at' => '2026-03-01 00:00:00']);

    expect(fn () => RegisterByInvitation::run($token, [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]))->toThrow(ValidationException::class);
});

it('[IAM-REG-08] token sah membuat user aktif di organisasi token itu', function () {
    $org = identityOrg();
    $token = invitationToken($org, ['used_count' => 0]);

    $user = RegisterByInvitation::run($token, [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]);

    expect($user->userProfile->verification_status)->toBe(VerificationStatus::Active)
        ->and($user->userProfile->organization_id)->toBe($org->id)
        ->and($token->fresh()->used_count)->toBe(1);
});

it('[IAM-REG-09] satu token dipakai beberapa user sampai kuotanya habis', function () {
    $token = invitationToken(identityOrg(), ['max_uses' => 3, 'used_count' => 0]);

    foreach (range(1, 3) as $i) {
        RegisterByInvitation::run($token->fresh(), [
            'name' => "Orang {$i}", 'email' => "orang{$i}@luar.co.id", 'password' => 'rahasia-panjang',
        ]);
    }

    expect($token->fresh()->used_count)->toBe(3);

    // Pemakaian keempat menabrak kuota.
    expect(fn () => RegisterByInvitation::run($token->fresh(), [
        'name' => 'Orang 4', 'email' => 'orang4@luar.co.id', 'password' => 'rahasia-panjang',
    ]))->toThrow(ValidationException::class);
});

it('[IAM-REG-11] token milik organisasi yang sudah dihapus ditolak', function () {
    $org = identityOrg();
    $token = invitationToken($org);

    // Lewat jalur yang sesungguhnya: menghapus organisasi mencabut undangan
    // yang belum terpakai (register #18), bukan meninggalkannya menggantung.
    $tercabut = DeleteOrganization::run($org);

    expect($tercabut)->toBe(1);

    expect(fn () => RegisterByInvitation::run($token->fresh(), [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]))->toThrow(ValidationException::class);
});

it('[IAM-REG-10] undangan belum terpakai tidak menghalangi penghapusan organisasi', function () {
    $org = identityOrg();
    invitationToken($org);
    invitationToken($org);

    // Operator diberi tahu berapa yang akan tercabut sebelum mengonfirmasi.
    expect(DeleteOrganization::run($org))->toBe(2)
        ->and(Organization::find($org->id))->toBeNull();
});

it('[IAM-REG-12] self-register memberi role researcher dan organisasi pilihannya', function () {
    $org = identityOrg();

    $user = RegisterResearcher::run(selfRegisterData($org));

    expect($user->hasRole('researcher'))->toBeTrue()
        ->and($user->userProfile->organization_id)->toBe($org->id)
        ->and($user->userProfile->verification_status)->toBe(VerificationStatus::Pending);
});

it('[IAM-REG-13] undangan mewariskan permission yang tertera di token', function () {
    Permission::findOrCreate('submissions.create', 'web');
    $token = invitationToken(identityOrg(), ['permissions' => ['submissions.create']]);

    $user = RegisterByInvitation::run($token, [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]);

    expect($user->can('submissions.create'))->toBeTrue()
        ->and($user->userProfile->verification_status)->toBe(VerificationStatus::Active);
});

it('[IAM-REG-14] pendaftar lewat undangan tidak masuk antrian verifikasi', function () {
    $token = invitationToken(identityOrg());

    $user = RegisterByInvitation::run($token, [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]);

    // Antrian dibangun dari status pending, bukan dari "belum diverifikasi" —
    // akun tanpa NIDN tidak akan pernah bisa diverifikasi dengan jujur.
    expect(UserProfile::awaitingVerification()->pluck('user_id')->all())->not->toContain($user->id);
});

it('[IAM-REG-15] dua akun undangan sama-sama tanpa NIDN dan tidak bentrok', function () {
    $token = invitationToken(identityOrg(), ['max_uses' => 3, 'used_count' => 0]);

    $rina = RegisterByInvitation::run($token->fresh(), [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]);
    $doni = RegisterByInvitation::run($token->fresh(), [
        'name' => 'Doni', 'email' => 'doni@luar.co.id', 'password' => 'rahasia-panjang',
    ]);

    expect($rina->userProfile->nidn)->toBeNull()
        ->and($doni->userProfile->nidn)->toBeNull()
        ->and($doni->userProfile->verification_status)->toBe(VerificationStatus::Active);
});

it('[IAM-REG-16] token yang dicabut Operator ditolak', function () {
    $token = invitationToken(identityOrg(), ['used_count' => 0]);

    $token->revoke();

    expect(fn () => RegisterByInvitation::run($token->fresh(), [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]))->toThrow(ValidationException::class);
});

it('[IAM-REG-17] token milik organisasi nonaktif ditolak', function () {
    $org = identityOrg();
    $token = invitationToken($org);

    $org->update(['is_active' => false]);

    expect(fn () => RegisterByInvitation::run($token->fresh(), [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]))->toThrow(ValidationException::class);
});

it('[IAM-REG-18] domain yang hanya menyerupai @itk.ac.id ditolak', function (string $email) {
    expect(fn () => RegisterResearcher::run(selfRegisterData(identityOrg(), ['email' => $email])))
        ->toThrow(ValidationException::class);
})->with([
    'budi@itk.ac.id.example.com',
    'budi@student.itk.ac.id',
    'budi@notitk.ac.id',
    'budi@itk.ac.idx',
]);

it('[IAM-REG-19] domain yang ditulis huruf besar tetap diterima', function () {
    $user = RegisterResearcher::run(selfRegisterData(identityOrg(), ['email' => 'Budi@ITK.AC.ID']));

    expect($user->userProfile->verification_status)->toBe(VerificationStatus::Pending);
});

it('[IAM-ORG-01] jalur HTTP menolak penghapusan unit yang masih berisi profil', function () {
    Permission::findOrCreate('users.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('users.manage');

    $org = identityOrg();
    UserProfile::factory()->create(['user_id' => User::factory()->create()->id, 'organization_id' => $org->id]);

    $this->actingAs($admin)
        ->delete(route('admin.organizations.destroy', $org))
        ->assertSessionHasErrors('organization_id');

    expect(Organization::find($org->id))->not->toBeNull();
});
