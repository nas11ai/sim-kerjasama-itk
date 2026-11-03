<?php

// tests/Feature/FormEngine/FormAccessRevokeTest.php
//
// Hapus kontrol akses lewat Action beraudit (BR-RPT-09); yang dipakai detail tahap
// tidak bisa dihapus (BR-FE-20). Route bersarang tab Access Control (ADMIN-08-07).

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Cache::flush();
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

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

it('audits a single delete and rejects one that is used (BR-FE-20, BR-RPT-09)', function () {
    $unused = ($this->grant)($this->faculty);
    $used = accessControlInUse();

    $this->actingAs($this->admin)
        ->delete(route('admin.forms.access.destroy', [$used->form_id, $used]))
        ->assertSessionHasErrors('id');

    $this->actingAs($this->admin)
        ->delete(route('admin.forms.access.destroy', [$unused->form_id, $unused]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.forms.access.index', $unused->form_id));

    expect(FormAccessControl::query()->whereKey($used->id)->exists())->toBeTrue()
        ->and(FormAccessControl::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(revokeAudit($used))->toBeNull()
        ->and(revokeAudit($unused)?->causer_id)->toBe($this->admin->id);
});
