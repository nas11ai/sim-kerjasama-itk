<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $permission = Permission::findOrCreate('users.manage');
    $admin = Role::findOrCreate('Admin');
    $admin->givePermissionTo($permission);
});

function statsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    return $user;
}

test('halaman statistik reviewer dirender tanpa error SQL', function () {
    $this->actingAs(statsAdmin())
        ->get(route('admin.stats.reviewer'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Statistics/SubmissionReviewerStats')
            ->hasAll([
                'reviewerRecent', 'totalReviewers', 'totalByRole', 'evaluationStatus',
                'reviewerByYear', 'reviewerByFaculty', 'reviewerByProdi',
                'reviewerActiveStatus', 'faculties', 'studyPrograms',
            ]));
});

test('halaman statistik pengajuan dirender tanpa error SQL', function () {
    $this->actingAs(statsAdmin())
        ->get(route('admin.stats.form-submission'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Statistics/FormSubmissionStats')
            ->hasAll([
                'recentSubmissions', 'totalSubmissions', 'totalByStatus', 'totalByFaculty',
                'totalByProdi', 'faculties', 'studyPrograms',
            ]));
});

test('halaman statistik fase form dan user dirender tanpa error SQL', function () {
    $admin = statsAdmin();

    $this->actingAs($admin)
        ->get(route('admin.stats.form-phase'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Statistics/FormPhaseStats')
            ->hasAll([
                'formPhaseFaculty', 'formPhaseProdi', 'formPhaseStatus', 'formPhaseTotal',
                'formPhaseByPeriod', 'faculties', 'studyPrograms',
            ]));

    $this->actingAs($admin)
        ->get(route('admin.stats.user'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Statistics/UserStats')
            ->hasAll([
                'userRecent', 'totalUsers', 'totalAdmin', 'totalNonAdmin',
                'totalProdi', 'totalFaculty',
            ]));
});

test('dasbor admin membawa keempat kelompok statistik', function () {
    $this->actingAs(statsAdmin())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DashboardPage')
            ->has('formPhase')
            ->has('formSubmission')
            ->has('user')
            ->has('submissionReviewer'));
});

test('endpoint JSON statistik untuk testing sudah tidak terdaftar', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.stats.get-form-phase',
    'admin.stats.get-form-submission',
    'admin.stats.get-reviewer',
    'admin.stats.get-user',
    'admin.stats.data',
]);
