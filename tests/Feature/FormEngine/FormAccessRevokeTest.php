<?php

// tests/Feature/FormEngine/FormAccessRevokeTest.php
//
// Hapus dan buat massal kontrol akses lewat Action beraudit (BR-RPT-09); hapus massal
// semua-atau-tidak (BR-FE-20); filter fakultas mencakup seluruh unit di bawahnya (ADR 0013).

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $this->faculty = Organization::create(['name' => 'Fakultas Hapus', 'type' => 'faculty']);
    $this->grant = fn (Organization $organization, ?Form $form = null): FormAccessControl => FormAccessControl::create([
        'form_id' => ($form ?? Form::factory()->create())->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
});

function revokeAudit(FormAccessControl $control, string $description = 'Kontrol akses formulir dihapus'): ?Activity
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $control->getMorphClass())
        ->where('subject_id', $control->id)
        ->where('description', $description)
        ->first();
}

function accessControlInUse(): FormAccessControl
{
    $control = FormPhaseDetail::factory()->create()->formAccessControl;

    return $control ?? throw new LogicException('Detail pabrik tanpa kontrol akses.');
}

it('deletes nothing in a bulk delete when one access control is used, with a readable reason (BR-FE-20)', function () {
    $unused = ($this->grant)($this->faculty);
    $used = accessControlInUse();
    $title = $used->form?->title;

    $this->actingAs($this->admin)
        ->post(route('admin.form-access-controls.bulk-delete'), ['ids' => [$unused->id, $used->id]])
        ->assertSessionHasErrors('id');

    expect(session('errors')->first('id'))->toContain('Tidak ada kontrol akses yang dihapus', (string) $title)
        ->and(FormAccessControl::query()->whereKey([$unused->id, $used->id])->count())->toBe(2)
        ->and(revokeAudit($unused))->toBeNull();
});

it('audits every access control removed by a bulk delete (BR-RPT-09)', function () {
    $first = ($this->grant)($this->faculty);
    $second = ($this->grant)($this->faculty);

    $this->actingAs($this->admin)
        ->post(route('admin.form-access-controls.bulk-delete'), ['ids' => [$first->id, $second->id]])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', '2 kontrol akses formulir berhasil dihapus.');

    expect(FormAccessControl::query()->whereKey([$first->id, $second->id])->exists())->toBeFalse()
        ->and(revokeAudit($first)?->causer_id)->toBe($this->admin->id)
        ->and(revokeAudit($second))->not->toBeNull();
});

it('audits a single delete and rejects one that is used (BR-FE-20, BR-RPT-09)', function () {
    $unused = ($this->grant)($this->faculty);
    $used = accessControlInUse();

    $this->actingAs($this->admin)
        ->delete(route('admin.form-access-controls.destroy', $used))
        ->assertSessionHasErrors('id');

    $this->actingAs($this->admin)
        ->delete(route('admin.form-access-controls.destroy', $unused))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.form-access-controls.index'));

    expect(FormAccessControl::query()->whereKey($used->id)->exists())->toBeTrue()
        ->and(FormAccessControl::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(revokeAudit($unused))->not->toBeNull();
});

it('audits a bulk grant and reports skipped duplicates in Indonesian (BR-RPT-09)', function () {
    $form = Form::factory()->create();
    ($this->grant)($this->faculty, $form);

    $this->actingAs($this->admin)
        ->post(route('admin.form-access-controls.bulk-create'), [
            'form_id' => $form->id,
            'combinations' => [
                ['permission' => 'submissions.create', 'study_program_id' => $this->faculty->id],
                ['permission' => 'submissions.view-own', 'study_program_id' => $this->faculty->id],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', '1 kontrol akses formulir dibuat; 1 dilewati karena sudah terdaftar.');

    $created = FormAccessControl::query()
        ->where(['form_id' => $form->id, 'permission' => 'submissions.view-own'])
        ->firstOrFail();

    expect(revokeAudit($created, 'Kontrol akses formulir dibuat')?->causer_id)->toBe($this->admin->id);
});

it('filters a faculty by its whole subtree, including the faculty itself (ADR 0013)', function () {
    $department = Organization::create(['name' => 'Jurusan', 'type' => 'department', 'parent_id' => $this->faculty->id]);
    $deepProgram = Organization::create([
        'name' => 'Prodi Dalam', 'type' => 'study_program', 'parent_id' => $department->id,
    ]);
    $otherFaculty = Organization::create(['name' => 'Fakultas Lain', 'type' => 'faculty']);

    $onFaculty = ($this->grant)($this->faculty);
    $onDeepProgram = ($this->grant)($deepProgram);
    $elsewhere = ($this->grant)($otherFaculty);

    $this->actingAs($this->admin)
        ->get(route('admin.form-access-controls.index', ['faculty_id' => $this->faculty->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('groupAccessControls.data', fn ($groups) => collect($groups)->pluck('form_id')->sort()->values()->all()
                === collect([$onFaculty->form_id, $onDeepProgram->form_id])->sort()->values()->all())
            ->has('groupAccessControls.data.0.controls')
            ->has('groupAccessControls.data.0.form')
            ->where('filters.faculty_id', (string) $this->faculty->id)
            ->has('forms')->has('permissions')->has('faculties'));

    expect($elsewhere->exists)->toBeTrue();
});

it('searches access controls by organization name, case-insensitively', function () {
    $found = ($this->grant)($this->faculty);
    ($this->grant)(Organization::create(['name' => 'Unit Lain', 'type' => 'faculty']));

    $this->actingAs($this->admin)
        ->get(route('admin.form-access-controls.index', ['search' => 'fakultas hapus']))
        ->assertInertia(fn ($page) => $page
            ->where('groupAccessControls.data', fn ($groups) => collect($groups)->pluck('form_id')->all() === [$found->form_id]));
});
