<?php

// tests/Feature/FormEngine/FormAccessGrantTest.php
//
// Memasang kontrol akses lewat Action beraudit (BR-RPT-09), pada organisasi aktif apa pun
// (ADR 0013), lewat route bersarang tab Access Control (ADMIN-08-07).

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\FormAccessService;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Cache::flush();
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

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

it('grants access on a faculty that a study program below it can reach (ADR 0013, BR-FE-02)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.forms.access.store', $this->form), [
            'permission' => 'submissions.create',
            'organization_id' => $this->faculty->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.forms.access.index', $this->form));

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
        ->post(route('admin.forms.access.store', $this->form), [
            'permission' => 'submissions.create',
            'organization_id' => $this->faculty->id,
        ])
        ->assertSessionHasErrors('organization_id');

    expect(FormAccessControl::query()->where('form_id', $this->form->id)->exists())->toBeFalse();
});
