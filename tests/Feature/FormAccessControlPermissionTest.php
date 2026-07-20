<?php

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('FormAccessControl organization subtree checks require PostgreSQL.');
    }

    Cache::flush();
});

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

test('accessibleBy scopes by spatie permissions and organization subtree', function () {
    $faculty = Organization::create([
        'name' => 'FSTI',
        'type' => 'faculty',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $prodi = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => $faculty->id,
        'is_active' => true,
    ]);

    $form = Form::factory()->create();

    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $prodi->id,
    ]);

    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'periods.manage',
        'organization_id' => $prodi->id,
    ]);

    $permission = Permission::findOrCreate('submissions.create');
    $role = Role::findOrCreate('researcher');
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    UserProfile::create([
        'user_id' => $user->id,
        'organization_id' => $faculty->id,
    ]);
    $user->assignRole($role);
    $user = $user->fresh(['userProfile', 'organization']);

    $matched = FormAccessControl::query()->accessibleBy($user)->pluck('permission')->all();

    expect($matched)->toBe(['submissions.create']);
});

test('permissionNamesFor uses spatie getAllPermissions only', function () {
    Permission::findOrCreate('reporting.export');
    Role::findOrCreate('Admin');

    $user = User::factory()->create();
    $user->givePermissionTo('reporting.export');
    $user->assignRole('Admin');

    $permissions = FormAccessControl::permissionNamesFor($user);

    expect($permissions)->toContain('reporting.export')
        ->and($permissions)->not->toContain('submissions.view-all');
});
