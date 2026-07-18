<?php

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\FormAccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('FormAccessService organization subtree checks require PostgreSQL.');
    }

    Cache::flush();
});

function createUserWithOrganization(Organization $organization): User
{
    $user = User::factory()->create();

    UserProfile::create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
    ]);

    return $user->fresh(['userProfile', 'organization']);
}

function createFormWithAccess(Organization $organization, string $permission = 'submissions.create'): Form
{
    $form = Form::factory()->create();

    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => $permission,
        'organization_id' => $organization->id,
    ]);

    return $form;
}

test('role-inherited permission grants form access', function () {
    $org = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $form = createFormWithAccess($org);

    $permission = Permission::findOrCreate('submissions.create');
    $role = Role::findOrCreate('researcher');
    $role->givePermissionTo($permission);

    $user = createUserWithOrganization($org);
    $user->assignRole($role);

    $service = app(FormAccessService::class);

    expect($service->canAccessForm($user, $form))->toBeTrue();
});

test('direct permission without role grants form access', function () {
    $org = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $form = createFormWithAccess($org);

    Permission::findOrCreate('submissions.create');
    $user = createUserWithOrganization($org);
    $user->givePermissionTo('submissions.create');

    $service = app(FormAccessService::class);

    expect($service->canAccessForm($user, $form))->toBeTrue();
});

test('user without permission cannot access form', function () {
    $org = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $form = createFormWithAccess($org);
    $user = createUserWithOrganization($org);

    $service = app(FormAccessService::class);

    expect($service->canAccessForm($user, $form))->toBeFalse();
});

test('parent organization can access descendant form access control', function () {
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
    $form = createFormWithAccess($prodi);

    Permission::findOrCreate('submissions.create');
    $user = createUserWithOrganization($faculty);
    $user->givePermissionTo('submissions.create');

    $service = app(FormAccessService::class);

    expect($service->canAccessForm($user, $form))->toBeTrue();
});

test('unrelated organization and missing organization deny form access', function () {
    $orgA = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $orgB = Organization::create([
        'name' => 'Sistem Informasi',
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $form = createFormWithAccess($orgA);

    Permission::findOrCreate('submissions.create');

    $unrelatedUser = createUserWithOrganization($orgB);
    $unrelatedUser->givePermissionTo('submissions.create');

    $userWithoutOrg = User::factory()->create();
    $userWithoutOrg->givePermissionTo('submissions.create');

    $service = app(FormAccessService::class);

    expect($service->canAccessForm($unrelatedUser, $form))->toBeFalse()
        ->and($service->canAccessForm($userWithoutOrg, $form))->toBeFalse();
});
