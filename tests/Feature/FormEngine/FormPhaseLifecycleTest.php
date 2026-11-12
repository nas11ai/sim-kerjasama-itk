<?php

// tests/Feature/FormEngine/FormPhaseLifecycleTest.php
//
// Membuat, menghapus tunggal, dan mengubah status tahap formulir lewat Action beraudit
// (BR-RPT-09). Hapus tunggal memakai penjaga BR-FE-20 yang sama dengan hapus massal.

use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->form = Form::factory()->create();
});

function auditEntry(string $description, FormPhase $phase): ?Activity
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $phase->getMorphClass())
        ->where('subject_id', $phase->id)
        ->where('description', $description)
        ->first();
}

/**
 * Baris detail baru, meminjam akses form dan tipe tahap milik detail pabrik.
 *
 * @return array<string, mixed>
 */
function newPhaseRow(): array
{
    $reference = FormPhaseDetail::factory()->create();
    $reference->formAccessControl?->update(['form_id' => test()->form->id]);

    return [
        'form_access_control_id' => $reference->form_access_control_id,
        'phase_type_id' => $reference->phase_type_id,
        'order' => 1,
        'needs_review' => false,
        'deadline_type' => 'submit',
        'deadline_cycle' => null,
    ];
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function createPayload(array $rows): array
{
    return ['title' => 'Tahap Baru', 'description' => 'Uji', 'is_active' => true, 'phase_details' => $rows];
}

it('saves a created phase with the deadline type of each detail (BR-FE-07)', function () {
    $row = newPhaseRow();

    $this->actingAs($this->admin)
        ->post(route('admin.phases.store'), createPayload([$row]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.phases.edit', FormPhase::query()->where('title', 'Tahap Baru')->firstOrFail()));

    $phase = FormPhase::query()->where('title', 'Tahap Baru')->firstOrFail();
    $detail = $phase->formPhaseDetails()->firstOrFail();

    expect($detail->deadline_type->value)->toBe($row['deadline_type'])
        ->and($detail->form_access_control_id)->toBe($row['form_access_control_id']);
});

it('rejects a created phase whose detail has no deadline type as validation (BR-FE-07)', function () {
    $row = newPhaseRow();
    unset($row['deadline_type']);

    $this->actingAs($this->admin)
        ->post(route('admin.phases.store'), createPayload([$row]))
        ->assertSessionHasErrors('phase_details.0.deadline_type');

    expect(FormPhase::query()->where('title', 'Tahap Baru')->exists())->toBeFalse();
});

it('audits the creation of a form phase (BR-RPT-09)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.phases.store'), createPayload([newPhaseRow()]))
        ->assertSessionHasNoErrors();

    $phase = FormPhase::query()->where('title', 'Tahap Baru')->firstOrFail();
    $entry = auditEntry('Tahap formulir dibuat', $phase);

    expect($entry)->not->toBeNull()
        ->and($entry?->causer_id)->toBe($this->admin->id);
});

it('rejects single delete of a phase used by a submission and keeps its evaluation forms (BR-FE-20)', function () {
    $detail = FormPhaseDetail::factory()->create();
    $phase = $detail->formPhase;
    $evaluation = evaluationFormOn($detail, [], title: 'Lembar Penilaian');
    FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.phases.destroy', $phase))
        ->assertSessionHasErrors('id');

    expect(FormPhase::query()->whereKey($phase->id)->exists())->toBeTrue()
        ->and(FormPhaseDetail::query()->whereKey($detail->id)->exists())->toBeTrue()
        ->and(ReviewEvaluationForm::query()->whereKey($evaluation->id)->exists())->toBeTrue();
});

it('audits a single delete of an unused phase (BR-RPT-09)', function () {
    $detail = FormPhaseDetail::factory()->create();
    $phase = $detail->formPhase;

    $this->actingAs($this->admin)
        ->from(route('admin.phases.index'))
        ->delete(route('admin.phases.destroy', $phase))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.phases.index'));

    expect(FormPhase::query()->whereKey($phase->id)->exists())->toBeFalse()
        ->and(auditEntry('Tahap formulir dihapus', $phase))->not->toBeNull();
});

it('mengaktifkan dan menonaktifkan tahap mengaudit perubahan, nilai sama tidak (BR-RPT-09)', function () {
    $detail = FormPhaseDetail::factory()->create();
    $phase = $detail->formPhase;
    $url = route('admin.phases.active', $phase);

    $this->actingAs($this->admin)->from('/back')->patch($url, ['active' => false])
        ->assertRedirect('/back')
        ->assertSessionHas('success', 'Tahap dinonaktifkan.');

    $entry = auditEntry('Status tahap formulir diubah', $phase);

    expect($phase->fresh()?->is_active)->toBeFalse()
        ->and($entry)->not->toBeNull()
        ->and($entry?->causer_id)->toBe($this->admin->id);

    $this->patch($url, ['active' => false])->assertSessionHasNoErrors();
    expect(Activity::inLog(AuditTrail::LOG_NAME)->where('description', 'Status tahap formulir diubah')->count())->toBe(1);

    $this->from('/back')->patch($url, ['active' => true])->assertSessionHas('success', 'Tahap diaktifkan.');
    expect($phase->fresh()?->is_active)->toBeTrue();
});

it('menolak kiriman active yang bukan boolean', function () {
    $detail = FormPhaseDetail::factory()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.phases.active', $detail->formPhase), [])
        ->assertSessionHasErrors('active');
});
