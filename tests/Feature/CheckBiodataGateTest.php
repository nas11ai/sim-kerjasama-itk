<?php

// Gerbang biodata (CheckBiodata). Reviewer dalam masa tugas dilewati memakai definisi
// yang sama dengan penugasan (BR-REV-09, inklusif per hari); sisanya diperiksa seperti
// user biasa dan bentuk status sesi `biodataStatus` tidak berubah.

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\Reviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use Spatie\Permission\Models\Role;

function biodataGateUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Mahasiswa', 'web'));

    return $user;
}

function biodataGateForm(): Form
{
    $type = FormType::forceCreate(['id' => FormType::BIODATA_ID, 'name' => 'Biodata']);

    return Form::factory()->create(['form_type_id' => $type->id, 'is_active' => true]);
}

it('[BR-REV-09] lets a reviewer whose tenure ends today skip the biodata gate', function () {
    biodataGateForm();
    $user = biodataGateUser();
    Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now(),
    ]);

    $this->actingAs($user)->get(route('user.announcements.index'))->assertOk();

    expect(session('biodataStatus'))->toBe([
        'required' => false,
        'completed' => true,
        'showAllMenus' => true,
    ]);
});

it('[BR-REV-09] checks the biodata of a reviewer whose tenure starts tomorrow', function () {
    biodataGateForm();
    $user = biodataGateUser();
    Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->addDay(),
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->get(route('user.announcements.index'))
        ->assertRedirect(route('user.biodata.index'));
});

it('reports not_submitted when no biodata was submitted', function () {
    $form = biodataGateForm();

    $this->actingAs(biodataGateUser())
        ->get(route('user.announcements.index'))
        ->assertRedirect(route('user.biodata.index'));

    expect(session('biodataStatus'))->toBe([
        'required' => true,
        'completed' => false,
        'showAllMenus' => false,
        'status' => 'not_submitted',
        'message' => 'Silakan lengkapi biodata Anda terlebih dahulu.',
        'form_id' => $form->id,
    ]);
});

it('reports a pending biodata and redirects to the dashboard', function () {
    $form = biodataGateForm();
    $user = biodataGateUser();
    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => true,
        'status' => Rejected::class,
    ]);

    $this->actingAs($user)
        ->get(route('user.announcements.index'))
        ->assertRedirect(route('user.dashboard'));

    expect(session('biodataStatus'))->toBe([
        'required' => true,
        'completed' => false,
        'showAllMenus' => false,
        'status' => 'rejected',
        'message' => 'Biodata Anda ditolak. Silakan perbaiki dan kirim ulang.',
        'submission_id' => $submission->id,
        'form_id' => $form->id,
        'can_edit' => true,
    ]);
});

it('reports an approved biodata and lets the request through', function () {
    $form = biodataGateForm();
    $user = biodataGateUser();
    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => true,
        'status' => Approved::class,
    ]);

    $this->actingAs($user)->get(route('user.announcements.index'))->assertOk();

    expect(session('biodataStatus'))->toBe([
        'required' => true,
        'completed' => true,
        'showAllMenus' => true,
        'status' => 'approved',
        'submission_id' => $submission->id,
    ]);
});

it('reports a missing biodata form without blocking', function () {
    $this->actingAs(biodataGateUser())->get(route('user.announcements.index'))->assertOk();

    expect(session('biodataStatus'))->toBe([
        'required' => false,
        'completed' => true,
        'showAllMenus' => true,
        'error' => true,
        'message' => 'Formulir biodata tidak ditemukan. Silakan hubungi administrator.',
    ]);
});
