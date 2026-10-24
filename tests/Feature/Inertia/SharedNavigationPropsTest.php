<?php

// Shared props untuk sidebar v2: permission efektif dan flag navigasi (fe-v2-design §4.4).

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\Queries\Identity\EffectivePermissionNames;
use App\States\Submission\Approved;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function approvedSubmissionFor(User $user, int $formTypeId): void
{
    $type = FormType::query()->find($formTypeId)
        ?? FormType::forceCreate(['id' => $formTypeId, 'name' => 'Tipe '.$formTypeId]);
    $form = Form::factory()->create(['form_type_id' => $type->id]);

    FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'status' => Approved::class,
    ]);
}

it('membagikan permission efektif peneliti, urut abjad', function () {
    $user = User::factory()->create();
    $user->assignRole('researcher');

    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
        ->where('auth.user.permissions', ['budget.edit', 'members.manage', 'outputs.manage', 'submissions.create', 'submissions.view-own']));
});

it('memberi Super Admin seluruh katalog permission', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Super Admin', 'web'));

    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
        ->where('auth.user.permissions', collect(PermissionSeeder::PERMISSIONS)->sort()->values()->all()));
});

it('flag hasApprovedSubmission false tanpa pengajuan disetujui', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
        ->where('navigation.hasApprovedSubmission', false));
});

it('flag hasApprovedSubmission true saat pengajuan riset milik user Approved', function () {
    $user = User::factory()->create();
    approvedSubmissionFor($user, 2);

    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
        ->where('navigation.hasApprovedSubmission', true));
});

it('biodata yang disetujui tidak menyalakan hasApprovedSubmission', function () {
    $user = User::factory()->create();
    approvedSubmissionFor($user, FormType::BIODATA_ID);

    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
        ->where('navigation.hasApprovedSubmission', false));
});

it('pengajuan disetujui milik orang lain tidak menyalakan flag', function () {
    $user = User::factory()->create();
    approvedSubmissionFor(User::factory()->create(), 2);

    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page
        ->where('navigation.hasApprovedSubmission', false));
});

it('partial reload yang tidak meminta auth tidak menghitung permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(function ($page) {
        EffectivePermissionNames::shouldNotRun();
        $page->reloadOnly('navigation');
    });
});

it('tamu menerima auth.user dan navigation null', function () {
    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->component('Auth/LoginPage')
        ->where('auth.user', null)
        ->where('navigation', null));
});
