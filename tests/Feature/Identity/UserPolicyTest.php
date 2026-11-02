<?php

// W47, W57 — wewenang per layar Identity: users.verify melihat dan memverifikasi,
// hanya users.manage yang menonaktifkan dan mengubah peran; tidak atas diri sendiri
// dan tidak atas akun Super Admin (BR-IAM-05, BR-IAM-09).

use App\Models\InvitationToken;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function policyActor(string ...$permissions): User
{
    $user = User::factory()->create(['is_active' => true]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

it('pemegang users.verify melihat user tapi tidak menonaktifkan', function () {
    $actor = policyActor('users.verify');
    $target = User::factory()->create();

    expect($actor->can('viewAny', User::class))->toBeTrue()
        ->and($actor->can('view', $target))->toBeTrue()
        ->and($actor->can('deactivate', $target))->toBeFalse()
        ->and($actor->can('changeRoles', $target))->toBeFalse()
        ->and($actor->can('managePermissions', $target))->toBeFalse();
});

it('pemegang users.manage berwenang penuh atas user lain', function () {
    $actor = policyActor('users.manage');
    $target = User::factory()->create();

    expect($actor->can('viewAny', User::class))->toBeTrue()
        ->and($actor->can('view', $target))->toBeTrue()
        ->and($actor->can('deactivate', $target))->toBeTrue()
        ->and($actor->can('changeRoles', $target))->toBeTrue()
        ->and($actor->can('managePermissions', $target))->toBeTrue();
});

it('pemegang users.manage tidak berwenang atas dirinya sendiri', function () {
    $actor = policyActor('users.manage');

    expect($actor->can('deactivate', $actor))->toBeFalse()
        ->and($actor->can('changeRoles', $actor))->toBeFalse()
        ->and($actor->can('managePermissions', $actor))->toBeFalse();
});

it('pemegang users.manage tidak berwenang atas akun Super Admin', function () {
    $actor = policyActor('users.manage');
    $target = User::factory()->create();
    Role::findOrCreate('Super Admin', 'web');
    $target->assignRole('Super Admin');

    expect($actor->can('deactivate', $target))->toBeFalse()
        ->and($actor->can('changeRoles', $target))->toBeFalse()
        ->and($actor->can('managePermissions', $target))->toBeFalse();
});

it('peneliti tidak berwenang apa pun atas user', function () {
    $actor = policyActor('submissions.create');
    $target = User::factory()->create();

    expect($actor->can('viewAny', User::class))->toBeFalse()
        ->and($actor->can('view', $target))->toBeFalse()
        ->and($actor->can('deactivate', $target))->toBeFalse();
});

it('undangan dikelola pemegang users.verify atau users.manage', function () {
    expect(policyActor('users.verify')->can('manage', InvitationToken::class))->toBeTrue()
        ->and(policyActor('users.manage')->can('manage', InvitationToken::class))->toBeTrue()
        ->and(policyActor('reviewers.assign')->can('manage', InvitationToken::class))->toBeFalse()
        ->and(policyActor('submissions.create')->can('manage', InvitationToken::class))->toBeFalse();
});
