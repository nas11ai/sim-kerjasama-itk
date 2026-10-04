<?php

// tests/Feature/FormEngine/FormAccessGrantTest.php
//
// Membuat dan mengubah kontrol akses formulir lewat Action beraudit (BR-RPT-09). Kontrol
// akses boleh dipasang pada organisasi aktif apa pun — ADR 0013: form terlihat oleh unit
// itu dan semua unit di bawahnya. Form kontrol akses yang dipakai terkunci (BR-FE-20).

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\FormAccessService;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $institution = Organization::create(['name' => 'ITK Uji', 'type' => 'institution']);
    $this->faculty = Organization::create([
        'name' => 'Fakultas Uji', 'type' => 'faculty', 'parent_id' => $institution->id,
    ]);
    $this->studyProgram = Organization::create([
        'name' => 'Prodi Uji', 'type' => 'study_program', 'parent_id' => $this->faculty->id,
    ]);
    $this->form = Form::factory()->create();
});

function accessAudit(string $description, FormAccessControl $control): ?Activity
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $control->getMorphClass())
        ->where('subject_id', $control->id)
        ->where('description', $description)
        ->first();
}

function usedAccessControl(): FormAccessControl
{
    $control = FormPhaseDetail::factory()->create()->formAccessControl;

    return $control ?? throw new LogicException('Detail pabrik tanpa kontrol akses.');
}

it('grants access on a faculty that a study program below it can reach (ADR 0013, BR-FE-02)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.form-access-controls.store'), [
            'form_id' => $this->form->id,
            'permission' => 'submissions.create',
            'study_program_id' => $this->faculty->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.form-access-controls.index'));

    $control = FormAccessControl::query()->where('organization_id', $this->faculty->id)->firstOrFail();

    $researcher = User::factory()->create();
    UserProfile::create(['user_id' => $researcher->id, 'organization_id' => $this->studyProgram->id]);
    $researcher->givePermissionTo(Permission::findOrCreate('submissions.create'));

    expect(app(FormAccessService::class)->canAccessForm($researcher->fresh(), $this->form))->toBeTrue()
        ->and(accessAudit('Kontrol akses formulir dibuat', $control)?->causer_id)->toBe($this->admin->id);
});

it('rejects access on an inactive organization (ADR 0013)', function () {
    $this->faculty->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->post(route('admin.form-access-controls.store'), [
            'form_id' => $this->form->id,
            'permission' => 'submissions.create',
            'study_program_id' => $this->faculty->id,
        ])
        ->assertSessionHasErrors('study_program_id');

    expect(FormAccessControl::query()->where('form_id', $this->form->id)->exists())->toBeFalse();
});

it('accepts a faculty in a bulk grant (ADR 0013)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.form-access-controls.bulk-create'), [
            'form_id' => $this->form->id,
            'combinations' => [['permission' => 'submissions.create', 'study_program_id' => $this->faculty->id]],
        ])
        ->assertSessionHasNoErrors();

    expect(FormAccessControl::query()->where('organization_id', $this->faculty->id)->exists())->toBeTrue();
});

it('rejects changing the form of an access control used by a phase detail (BR-FE-20)', function () {
    $control = usedAccessControl();
    $formId = $control->form_id;

    $this->actingAs($this->admin)
        ->patch(route('admin.form-access-controls.update', $control), [
            'form_id' => $this->form->id,
            'permission' => $control->permission,
            'study_program_id' => $control->organization_id,
        ])
        ->assertSessionHasErrors('form_id');

    expect($control->fresh()?->form_id)->toBe($formId)
        ->and(accessAudit('Kontrol akses formulir diubah', $control))->toBeNull();
});

it('saves a new permission and organization for a used access control, audited (BR-RPT-09)', function () {
    $control = usedAccessControl();

    $this->actingAs($this->admin)
        ->patch(route('admin.form-access-controls.update', $control), [
            'form_id' => $control->form_id,
            'permission' => 'submissions.view-own',
            'study_program_id' => $this->faculty->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.form-access-controls.index'));

    $fresh = $control->fresh();

    expect($fresh?->permission)->toBe('submissions.view-own')
        ->and($fresh?->organization_id)->toBe($this->faculty->id)
        ->and(accessAudit('Kontrol akses formulir diubah', $control)?->causer_id)->toBe($this->admin->id);
});
