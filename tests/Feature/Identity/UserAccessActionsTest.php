<?php

// BR-IAM-09, BR-RPT-09, W57 — peran dan hak akses langsung diubah lewat Action beraudit.
// Sinkronisasi peran hanya menyentuh RoleCatalog::ASSIGNABLE; reviewer lewat penunjukan
// (BR-IAM-11), role legacy tidak tersentuh. Tak ada aksi atas diri sendiri atau Super Admin.

use App\Actions\Identity\ChangeUserRoles;
use App\Actions\Identity\GrantDirectPermission;
use App\Actions\Identity\RevokeDirectPermission;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function accessAdmin(string $permission = 'users.manage'): User
{
    $admin = User::factory()->create(['is_active' => true]);
    Permission::findOrCreate($permission, 'web');
    $admin->givePermissionTo($permission);

    return $admin;
}

function accessRoles(): void
{
    foreach (['researcher', 'operator', 'admin', 'reviewer_internal', 'Tenaga Kependidikan', 'Super Admin'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    Permission::findOrCreate('reporting.export', 'web');
}

it('peran di luar ASSIGNABLE tidak tersentuh', function () {
    accessRoles();
    $user = User::factory()->create();
    $user->assignRole(['reviewer_internal', 'Tenaga Kependidikan', 'researcher']);

    ChangeUserRoles::run(accessAdmin(), $user, ['operator']);

    expect($user->fresh()->getRoleNames()->sort()->values()->all())
        ->toBe(['Tenaga Kependidikan', 'operator', 'reviewer_internal']);
});

it('peran reviewer di input ditolak', function () {
    accessRoles();
    $user = User::factory()->create();

    expect(fn () => ChangeUserRoles::run(accessAdmin(), $user, ['reviewer_internal']))
        ->toThrow(ValidationException::class);
    expect($user->fresh()->getRoleNames()->all())->toBe([]);
});

it('aksi atas diri sendiri ditolak', function () {
    accessRoles();
    $admin = accessAdmin();
    $admin->assignRole('admin');

    $this->actingAs($admin)->put(route('admin.users.roles.update', $admin), ['roles' => []])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.deactivate', $admin))->assertForbidden();

    expect($admin->fresh()->hasRole('admin'))->toBeTrue()
        ->and($admin->fresh()->is_active)->toBeTrue();
});

it('akun Super Admin tidak bisa disunting', function () {
    accessRoles();
    $root = User::factory()->create();
    $root->assignRole('Super Admin');
    $admin = accessAdmin();

    $this->actingAs($admin)->put(route('admin.users.roles.update', $root), ['roles' => ['researcher']])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.permissions.grant', $root), ['permission' => 'reporting.export'])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.deactivate', $root))->assertForbidden();

    expect($root->fresh()->getRoleNames()->all())->toBe(['Super Admin']);
});

it('grant/revoke/change mengaudit', function () {
    accessRoles();
    $admin = accessAdmin();
    $user = User::factory()->create();

    GrantDirectPermission::run($admin, $user, 'reporting.export');
    RevokeDirectPermission::run($admin, $user, 'reporting.export');
    ChangeUserRoles::run($admin, $user, ['researcher']);

    $entries = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->where('subject_id', $user->id)->orderBy('id')->get();
    expect($entries->pluck('description')->all())
        ->toBe(['Hak akses langsung diberikan', 'Hak akses langsung dicabut', 'Peran user diubah'])
        ->and($entries->pluck('causer_id')->unique()->all())->toBe([$admin->id])
        ->and($entries[0]->properties['new'])->toBe(['permission' => 'reporting.export'])
        ->and($entries[2]->properties['old'])->toBe(['roles' => []])
        ->and($entries[2]->properties['new'])->toBe(['roles' => ['researcher']]);
});

it('permission di luar katalog ditolak', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('legacy.thing', 'web');

    expect(fn () => GrantDirectPermission::run(accessAdmin(), $user, 'legacy.thing'))->toThrow(ValidationException::class)
        ->and(fn () => RevokeDirectPermission::run(accessAdmin(), $user, 'legacy.thing'))->toThrow(ValidationException::class);
});

it('mengubah peran lewat route', function () {
    accessRoles();
    $user = User::factory()->create();

    $this->actingAs(accessAdmin())->put(route('admin.users.roles.update', $user), ['roles' => ['operator', 'researcher']])
        ->assertRedirect()
        ->assertSessionHas('success', 'Peran user diperbarui.');

    expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['operator', 'researcher']);
});

it('Operator tanpa users.manage mendapat 403 di deactivate, roles, permissions', function () {
    accessRoles();
    $operator = accessAdmin('users.verify');
    $user = User::factory()->create();

    $this->actingAs($operator)->post(route('admin.users.deactivate', $user))->assertForbidden();
    $this->actingAs($operator)->put(route('admin.users.roles.update', $user), ['roles' => ['operator']])->assertForbidden();
    $this->actingAs($operator)->post(route('admin.users.permissions.grant', $user), ['permission' => 'reporting.export'])->assertForbidden();
    $this->actingAs($operator)->delete(route('admin.users.permissions.revoke', $user), ['permission' => 'reporting.export'])->assertForbidden();

    expect($user->fresh()->is_active)->toBeTrue()
        ->and($user->fresh()->getRoleNames()->all())->toBe([]);
});

it('route admin.users.edit dan admin.users.update tidak ada lagi', function () {
    expect(Route::has('admin.users.edit'))->toBeFalse()
        ->and(Route::has('admin.users.update'))->toBeFalse();
});
