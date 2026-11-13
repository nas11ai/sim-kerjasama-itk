<?php

// ADMIN-08-07 — tab Access Control: kontrak, tambah/hapus aturan, wewenang `schemes.manage` (W88).

use App\Actions\Form\GrantFormAccess;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->form = Form::factory()->create();
    $this->faculty = Organization::create(['name' => 'Fakultas Akses', 'type' => 'faculty']);
});

function accessTabActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function accessTabAudit(string $description, FormAccessControl $rule): ?Activity
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $rule->getMorphClass())
        ->where('subject_id', $rule->id)
        ->where('description', $description)
        ->first();
}

it('kontrak tab access control', function () {
    $program = Organization::create(['name' => 'Prodi Akses', 'type' => 'study_program', 'parent_id' => $this->faculty->id]);
    $rule = FormAccessControl::create([
        'form_id' => $this->form->id, 'permission' => 'submissions.create', 'organization_id' => $program->id,
    ]);

    $this->actingAs(accessTabActor())
        ->get(route('admin.forms.access.index', $this->form))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/FormEditPage')
            ->where('tab', 'access')
            ->where('form', ['id' => $this->form->id, 'title' => $this->form->title, 'isActive' => $this->form->is_active])
            ->where('rules.0', [
                'id' => $rule->id,
                'permission' => 'submissions.create',
                'permissionLabel' => 'Membuat pengajuan',
                'permissionDescription' => 'Membuat dan mengirim pengajuan baru.',
                'organizationId' => $program->id,
                'organizationLabel' => 'Prodi Akses',
                'phaseCount' => 0,
            ])
            ->where('permissionOptions.0', [
                'value' => 'submissions.create',
                'label' => 'Membuat pengajuan',
                'description' => 'Membuat dan mengirim pengajuan baru.',
            ])
            ->has('permissionOptions', count(FormAccessControl::ALLOWED_PERMISSIONS))
            ->where('organizationOptions', [
                ['value' => (string) $this->faculty->id, 'label' => 'Fakultas Akses — beserta unit di bawahnya'],
                ['value' => (string) $program->id, 'label' => 'Prodi Akses'],
            ]));
});

it('label organisasi menyebut unit di bawahnya hanya bila ada turunan aktif (ADR 0013)', function () {
    $program = Organization::create(['name' => 'Prodi Akses', 'type' => 'study_program', 'parent_id' => $this->faculty->id]);
    $program->update(['is_active' => false]);

    $this->actingAs(accessTabActor())
        ->get(route('admin.forms.access.index', $this->form))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('organizationOptions', [['value' => (string) $this->faculty->id, 'label' => 'Fakultas Akses']]));
});

it('menambah aturan mengaudit dan menolak duplikat', function () {
    $admin = accessTabActor();
    $payload = ['permission' => 'submissions.create', 'organization_id' => $this->faculty->id];

    $this->actingAs($admin)
        ->post(route('admin.forms.access.store', $this->form), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.forms.access.index', $this->form))
        ->assertSessionHas('success', 'Aturan akses ditambahkan.');

    $rule = FormAccessControl::query()->where('form_id', $this->form->id)->sole();
    expect(accessTabAudit('Kontrol akses formulir dibuat', $rule)?->causer_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('admin.forms.access.store', $this->form), $payload)
        ->assertSessionHasErrors(['duplicate' => GrantFormAccess::DUPLICATE_MESSAGE]);
    expect(FormAccessControl::query()->where('form_id', $this->form->id)->count())->toBe(1);
});

it('menolak permission di luar daftar dan organisasi kosong', function () {
    $this->actingAs(accessTabActor())
        ->post(route('admin.forms.access.store', $this->form), ['permission' => 'users.manage'])
        ->assertSessionHasErrors(['permission', 'organization_id']);
});

it('organisasi nonaktif ditolak', function () {
    $this->faculty->update(['is_active' => false]);

    $this->actingAs(accessTabActor())
        ->post(route('admin.forms.access.store', $this->form), [
            'permission' => 'submissions.create', 'organization_id' => $this->faculty->id,
        ])
        ->assertSessionHasErrors('organization_id');
    expect(FormAccessControl::query()->exists())->toBeFalse();
});

it('menghapus aturan mengaudit (BR-RPT-09)', function () {
    $admin = accessTabActor();
    $rule = FormAccessControl::create([
        'form_id' => $this->form->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.forms.access.destroy', [$this->form, $rule]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.forms.access.index', $this->form))
        ->assertSessionHas('success', 'Aturan akses dihapus.');

    expect(FormAccessControl::query()->whereKey($rule->id)->exists())->toBeFalse()
        ->and(accessTabAudit('Kontrol akses formulir dihapus', $rule)?->causer_id)->toBe($admin->id);
});

it('aturan yang dipakai tahap terbit tidak bisa dihapus dan menyebut angkanya (BR-FE-20)', function () {
    $rule = FormPhaseDetail::factory()->create()->formAccessControl ?? throw new LogicException('Detail tanpa aturan.');
    $admin = accessTabActor();

    $this->actingAs($admin)
        ->get(route('admin.forms.access.index', $rule->form_id))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('rules.0.phaseCount', 1));

    $this->actingAs($admin)
        ->delete(route('admin.forms.access.destroy', [$rule->form_id, $rule]))
        ->assertSessionHasErrors('id');

    expect(session('errors')->first('id'))->toContain('(1 tahap)')
        ->and(FormAccessControl::query()->whereKey($rule->id)->exists())->toBeTrue();
});

it('aturan di beberapa versi satu tahap dihitung satu tahap, dan pesannya tidak menyuruh melepas dari tahap (BR-FE-32)', function () {
    $rule = FormAccessControl::create(['form_id' => $this->form->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id]);
    $phase = FormPhase::create(['title' => 'Hibah Internal', 'is_active' => true]);
    $step = ['form_access_control_id' => $rule->id, 'phase_type_id' => PhaseType::create(['name' => 'Tipe Akses'])->id, 'order' => 1, 'deadline_type' => 'submit'];
    phaseVersion($phase, [$step]);
    phaseVersion($phase, [$step]);
    phaseVersion($phase, []);
    $admin = accessTabActor();

    $this->actingAs($admin)
        ->get(route('admin.forms.access.index', $this->form))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('rules.0.phaseCount', 1));
    $this->actingAs($admin)->delete(route('admin.forms.access.destroy', [$this->form, $rule]))->assertSessionHasErrors('id');

    expect(session('errors')->first('id'))
        ->toContain('versi tahap yang sudah terbit')
        ->not->toContain('Lepaskan dari tahapnya');
});

it('aturan form lain tidak bisa dihapus lewat URL form ini', function () {
    $other = FormAccessControl::create([
        'form_id' => Form::factory()->create()->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id,
    ]);

    $this->actingAs(accessTabActor())
        ->delete(route('admin.forms.access.destroy', [$this->form, $other]))
        ->assertNotFound();
    expect(FormAccessControl::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('wewenang: peneliti 403', function () {
    $researcher = accessTabActor('researcher');
    $rule = FormAccessControl::create([
        'form_id' => $this->form->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id,
    ]);

    $this->actingAs($researcher)->get(route('admin.forms.access.index', $this->form))->assertForbidden();
    $this->actingAs($researcher)
        ->post(route('admin.forms.access.store', $this->form), ['permission' => 'submissions.create', 'organization_id' => $this->faculty->id])
        ->assertForbidden();
    $this->actingAs($researcher)->delete(route('admin.forms.access.destroy', [$this->form, $rule]))->assertForbidden();
    expect(FormAccessControl::query()->count())->toBe(1);
});

it('update dengan continue menuju tab access control', function () {
    $form = Form::factory()->create(['form_type_id' => regularFormType()->id]);

    $this->actingAs(accessTabActor())
        ->put(route('admin.forms.update', $form), formEditPayload($form->form_type_id, ['continue' => true]))
        ->assertRedirect(route('admin.forms.access.index', $form));
});
