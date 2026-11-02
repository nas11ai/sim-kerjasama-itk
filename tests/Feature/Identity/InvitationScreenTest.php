<?php

// ADMIN-08-01 — kontrak layar undangan, preset hak akses (W51), dan wewenang Operator (W47).

use App\Actions\Identity\RevokeInvitation;
use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function screenOrg(bool $active = true): Organization
{
    return Organization::create([
        'name' => 'Unit '.fake()->unique()->numerify('####'),
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => $active,
    ]);
}

/** @param  array<string, mixed>  $override */
function screenToken(Organization $org, array $override = []): InvitationToken
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

function invitationIssuer(string $role = 'operator'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array<string, mixed> */
function invitationPayload(int $organizationId, array $override = []): array
{
    return array_merge([
        'organization_id' => $organizationId,
        'preset' => 'researcher',
        'max_uses' => 5,
        'expires_at' => now()->addMonth()->toDateString(),
    ], $override);
}

it('kontrak layar undangan', function () {
    $org = screenOrg();
    $token = screenToken($org, [
        'permissions' => PermissionSeeder::ROLE_PERMISSIONS['researcher'],
        'max_uses' => 5,
        'used_count' => 3,
    ]);
    screenOrg(false);

    $this->actingAs(invitationIssuer())
        ->get(route('admin.invitations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Identity/InvitationIndexPage')
            ->has('invitations', 1)
            ->has('invitations.0', fn (AssertableInertia $row) => $row
                ->where('id', $token->id)
                ->where('organization', $org->name)
                ->where('organizationId', $org->id)
                ->where('preset', ['value' => 'researcher', 'label' => 'Membuat & mengelola pengajuan'])
                ->where('usedCount', 3)
                ->where('maxUses', 5)
                ->where('remainingUses', 2)
                ->where('expiresAt', $token->expires_at?->toIso8601String())
                ->where('status', ['value' => 'valid', 'label' => 'Valid'])
                ->where('revocable', true))
            // Organisasi nonaktif tidak ditawarkan (BR-IAM-14).
            ->where('organizationOptions', [['value' => (string) $org->id, 'label' => $org->name]])
            ->where('presetOptions', [['value' => 'researcher', 'label' => 'Membuat & mengelola pengajuan']]));
});

it('[IAM-INVITE-02] peneliti 403 di index dan store', function () {
    $researcher = invitationIssuer('researcher');

    $this->actingAs($researcher)->get(route('admin.invitations.index'))->assertForbidden();
    $this->actingAs($researcher)
        ->post(route('admin.invitations.store'), invitationPayload(screenOrg()->id))
        ->assertForbidden();

    expect(InvitationToken::count())->toBe(0);
});

it('[IAM-INVITE-05] peneliti 403 di revoke', function () {
    $token = screenToken(screenOrg());

    $this->actingAs(invitationIssuer('researcher'))
        ->delete(route('admin.invitations.revoke', $token))
        ->assertForbidden();

    expect($token->fresh()->revoked_at)->toBeNull();
});

it('[IAM-INVITE-09] peran operator (users.verify) menerbitkan dan mencabut undangan', function () {
    $operator = invitationIssuer();
    expect($operator->can('users.manage'))->toBeFalse();

    $this->actingAs($operator)
        ->post(route('admin.invitations.store'), invitationPayload(screenOrg()->id))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $token = InvitationToken::sole();
    expect($token->isValid())->toBeTrue();

    $this->actingAs($operator)
        ->delete(route('admin.invitations.revoke', $token))
        ->assertRedirect();

    expect($token->fresh()->revoked_at)->not->toBeNull()
        ->and($token->fresh()->isValid())->toBeFalse();
});

it('pemegang reviewers.assign saja 403', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('reviewers.assign');

    $this->actingAs($user)->get(route('admin.invitations.index'))->assertForbidden();
});

it('status tiap sebab', function (array $override, bool $orgActive, string $value, string $label) {
    $org = screenOrg($orgActive);
    screenToken($org, $override);

    $this->actingAs(invitationIssuer())
        ->get(route('admin.invitations.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invitations.0.status', ['value' => $value, 'label' => $label])
            ->etc());
})->with([
    'valid' => [[], true, 'valid', 'Valid'],
    'dicabut' => [['revoked_at' => now()->subHour(), 'used_count' => 5], true, 'revoked', 'Dicabut'],
    'organisasi nonaktif' => [['used_count' => 5], false, 'organization_inactive', 'Organisasi Nonaktif'],
    'kuota habis' => [['used_count' => 5, 'expires_at' => now()->subDay()], true, 'exhausted', 'Kuota Habis'],
    'kedaluwarsa' => [['expires_at' => now()->subDay()], true, 'expired', 'Kedaluwarsa'],
]);

it('[IAM-INVITE-06] undangan dicabut tetap terbaca dengan used_count', function () {
    $operator = invitationIssuer();
    $token = screenToken(screenOrg(), ['max_uses' => 10, 'used_count' => 1]);
    RevokeInvitation::run($operator, $token);

    $this->actingAs($operator)
        ->get(route('admin.invitations.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invitations.0.status.label', 'Dicabut')
            ->where('invitations.0.usedCount', 1)
            ->where('invitations.0.maxUses', 10)
            ->where('invitations.0.revocable', false)
            ->etc());
});

it('permission di luar preset diabaikan', function () {
    $this->actingAs(invitationIssuer())
        ->post(route('admin.invitations.store'), invitationPayload(screenOrg()->id, [
            'permissions' => ['users.manage', 'reviewers.assign'],
        ]))
        ->assertSessionHasNoErrors();

    $permissions = InvitationToken::sole()->permissions;
    sort($permissions);
    $expected = PermissionSeeder::ROLE_PERMISSIONS['researcher'];
    sort($expected);

    expect($permissions)->toBe($expected);
});

it('preset tak dikenal ditolak', function () {
    $this->actingAs(invitationIssuer())
        ->post(route('admin.invitations.store'), invitationPayload(screenOrg()->id, ['preset' => 'admin']))
        ->assertSessionHasErrors('preset');

    expect(InvitationToken::count())->toBe(0);
});

it('maks. pemakaian dan berlaku sampai wajib', function () {
    $this->actingAs(invitationIssuer())
        ->post(route('admin.invitations.store'), invitationPayload(screenOrg()->id, ['max_uses' => '', 'expires_at' => '']))
        ->assertSessionHasErrors(['max_uses', 'expires_at']);
});

it('expires_at disimpan pukul 23:59:59', function () {
    $date = now()->addDays(10)->toDateString();

    $this->actingAs(invitationIssuer())
        ->post(route('admin.invitations.store'), invitationPayload(screenOrg()->id, ['expires_at' => $date]))
        ->assertSessionHasNoErrors();

    expect(InvitationToken::sole()->expires_at?->format('Y-m-d H:i:s'))->toBe("{$date} 23:59:59");
});

it('tautan undangan dikirim sebagai flash dan tidak ada di props daftar', function () {
    $operator = invitationIssuer();

    $this->actingAs($operator)
        ->post(route('admin.invitations.store'), invitationPayload(screenOrg()->id))
        ->assertInertiaFlash('issuedInvitationUrl', route('register.invitation.show', InvitationToken::sole()->token));

    $this->actingAs($operator)
        ->get(route('admin.invitations.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('invitations.0.token')
            ->missing('invitations.0.url')
            ->etc());
});

it('registrasi undangan menampilkan label permission', function () {
    $token = screenToken(screenOrg(), ['permissions' => ['submissions.create']]);

    $this->get(route('register.invitation.show', $token->token))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('permissions', ['Membuat pengajuan'])
            ->etc());
});
