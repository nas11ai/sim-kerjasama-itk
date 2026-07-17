<?php

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('permissionForRoleName maps ddd and legacy roles', function () {
    expect(FormAccessControl::permissionForRoleName('researcher'))->toBe('submissions.create')
        ->and(FormAccessControl::permissionForRoleName('Mahasiswa'))->toBe('submissions.create')
        ->and(FormAccessControl::permissionForRoleName('reviewer_internal'))->toBe('reviewers.evaluate')
        ->and(FormAccessControl::permissionForRoleName('operator'))->toBe('periods.manage')
        ->and(FormAccessControl::permissionForRoleName('Admin'))->toBe('submissions.view-all')
        ->and(FormAccessControl::permissionForRoleName('unknown'))->toBeNull();
});

test('form access control stores permission instead of role_id', function () {
    $org = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $form = Form::factory()->create();

    $control = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $org->id,
    ]);

    expect($control->fresh()->permission)->toBe('submissions.create')
        ->and($control->fresh()->getAttributes())->not->toHaveKey('role_id');
});

test('accessibleBy scopes by effective permissions from roles', function () {
    $org = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $form = Form::factory()->create();

    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $org->id,
    ]);

    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'periods.manage',
        'organization_id' => $org->id,
    ]);

    Role::findOrCreate('Mahasiswa');
    $user = User::factory()->create();
    $user->assignRole('Mahasiswa');

    $matched = FormAccessControl::query()->accessibleBy($user)->pluck('permission')->all();

    expect($matched)->toBe(['submissions.create']);
});

test('effectivePermissionsFor merges spatie permissions and role map', function () {
    Permission::findOrCreate('reporting.export');
    Role::findOrCreate('Admin');

    $user = User::factory()->create();
    $user->givePermissionTo('reporting.export');
    $user->assignRole('Admin');

    $effective = FormAccessControl::effectivePermissionsFor($user);

    expect($effective)->toContain('reporting.export')
        ->and($effective)->toContain('submissions.view-all');
});
