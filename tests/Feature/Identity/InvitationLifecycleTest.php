<?php

// tests/Feature/Identity/InvitationLifecycleTest.php
//
// BR-IAM-16 — penerbitan dan pencabutan undangan (ADMIN-08-01).
//
// Sebelum aturan ini, InvitationToken::revoke() sudah ada di model dan
// [IAM-REG-16] sudah hijau — tapi tidak ada Action, Policy, controller, maupun
// route yang memanggilnya. Skenarionya membuktikan perilaku yang tidak bisa
// dipicu siapa pun, dan tautan yang bocor hanya bisa ditunggu kedaluwarsa.

use App\Actions\Identity\IssueInvitation;
use App\Actions\Identity\RegisterByInvitation;
use App\Actions\Identity\RevokeInvitation;
use App\Models\InvitationToken;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function operatorUndangan(): User
{
    Permission::findOrCreate('users.manage', 'web');

    $user = User::factory()->create(['is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id]);
    $user->givePermissionTo('users.manage');

    return $user->fresh();
}

function penelitiBiasa(): User
{
    $user = User::factory()->create(['is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id]);

    return $user->fresh();
}

it('[IAM-INVITE-01] operator menerbitkan undangan untuk sebuah organisasi', function () {
    $operator = operatorUndangan();
    $org = identityOrg();

    $undangan = IssueInvitation::run($operator, $org, [
        'permissions' => ['submissions.create'],
        'max_uses' => 5,
        'expires_at' => now()->addDays(30)->toDateTimeString(),
    ]);

    expect($undangan->organization_id)->toBe($org->id)
        ->and($undangan->isValid())->toBeTrue()
        ->and($undangan->used_count)->toBe(0)
        // Token diacak di sisi server; penerbit tidak memilihnya.
        ->and(strlen($undangan->token))->toBe(64);
});

it('[IAM-INVITE-02] peneliti biasa tidak bisa menerbitkan undangan', function () {
    $budi = penelitiBiasa();

    expect(fn () => IssueInvitation::run($budi, identityOrg()))
        ->toThrow(AuthorizationException::class)
        ->and(InvitationToken::query()->count())->toBe(0);
});

it('[IAM-INVITE-03] operator mencabut undangan yang kuotanya masih tersisa', function () {
    $operator = operatorUndangan();
    $undangan = invitationToken(identityOrg(), ['max_uses' => 10, 'used_count' => 1]);

    RevokeInvitation::run($operator, $undangan);

    expect($undangan->fresh()->isValid())->toBeFalse()
        // Dicabut, bukan dihapus: jejak kenapa sebuah tautan dimatikan sama
        // pentingnya dengan matinya.
        ->and(InvitationToken::query()->whereKey($undangan->id)->exists())->toBeTrue();

    expect(fn () => RegisterByInvitation::run($undangan->fresh(), [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]))->toThrow(ValidationException::class);
});

it('[IAM-INVITE-04] pencabutan tidak menyentuh akun yang terlanjur lahir', function () {
    $operator = operatorUndangan();
    $undangan = invitationToken(identityOrg(), ['used_count' => 0, 'permissions' => ['submissions.create']]);
    Permission::findOrCreate('submissions.create', 'web');

    $rina = RegisterByInvitation::run($undangan, [
        'name' => 'Rina', 'email' => 'rina@luar.co.id', 'password' => 'rahasia-panjang',
    ]);

    RevokeInvitation::run($operator, $undangan->fresh());

    expect($rina->fresh()->is_active)->toBeTrue()
        ->and($rina->fresh()->can('submissions.create'))->toBeTrue()
        ->and($rina->fresh()->userProfile->organization_id)->toBe($undangan->organization_id);
});

it('[IAM-INVITE-05] peneliti biasa tidak bisa mencabut undangan', function () {
    $undangan = invitationToken(identityOrg());

    expect(fn () => RevokeInvitation::run(penelitiBiasa(), $undangan))
        ->toThrow(AuthorizationException::class)
        ->and($undangan->fresh()->isValid())->toBeTrue();
});

it('[IAM-INVITE-06] undangan yang dicabut tetap terbaca sebagai riwayat', function () {
    $operator = operatorUndangan();
    $undangan = invitationToken(identityOrg(), ['max_uses' => 10, 'used_count' => 3]);

    RevokeInvitation::run($operator, $undangan);

    $this->actingAs($operator)
        ->get(route('admin.invitations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/InvitationsPage')
            ->where('invitations.0.revoked_at', fn ($v) => $v !== null)
            ->where('invitations.0.used_count', 3)
            // Token-nya sendiri tidak ikut ke layar: daftar memperlihatkan
            // keadaan undangan, bukan kredensialnya.
            ->missing('invitations.0.token')
        );
});

it('[IAM-INVITE-07] tautan undangan yang sah menampilkan form registrasi', function () {
    $undangan = invitationToken(identityOrg());

    $this->get(route('register.invitation.show', $undangan->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterInvitationPage')
            ->where('organization', $undangan->organization->name)
        );
});

// BR-IAM-06 — hak akses dari token ditampilkan read-only sebelum mendaftar.
it('[IAM-INVITE-07] form registrasi undangan menampilkan hak akses token, urut abjad', function () {
    $undangan = invitationToken(identityOrg(), ['permissions' => ['submissions.view-own', 'submissions.create']]);

    $this->get(route('register.invitation.show', $undangan->token))
        ->assertInertia(fn ($page) => $page
            ->where('token', $undangan->token)
            ->where('permissions', ['submissions.create', 'submissions.view-own'])
        );
});

it('[IAM-INVITE-08] tautan undangan tidak berlaku mendarat di layar penolakan', function (array $override) {
    $undangan = invitationToken(identityOrg(), $override);

    $this->get(route('register.invitation.show', $undangan->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/InvitationInvalidPage'));
})->with([
    'kuota habis' => [['max_uses' => 2, 'used_count' => 2]],
    'kedaluwarsa' => [['expires_at' => '2020-01-01 00:00:00']],
    'dicabut' => [['revoked_at' => '2026-01-01 00:00:00']],
]);

it('[IAM-INVITE-08] organisasi nonaktif membuat tautannya mendarat di layar penolakan', function () {
    $org = identityOrg();
    $undangan = invitationToken($org);
    $org->update(['is_active' => false]);

    $this->get(route('register.invitation.show', $undangan->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/InvitationInvalidPage'));
});

it('[IAM-INVITE-08] token yang tidak dikenal diperlakukan sama dengan yang tidak berlaku', function () {
    // Membedakan "tidak ada" dari "tidak berlaku" memberi tahu penebak bahwa
    // tebakannya mendekati.
    $this->get(route('register.invitation.show', str_repeat('x', 64)))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/InvitationInvalidPage'));
});
