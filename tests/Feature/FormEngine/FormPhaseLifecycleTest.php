<?php

// tests/Feature/FormEngine/FormPhaseLifecycleTest.php
//
// Membuat, menghapus tunggal, dan mengubah status tahap formulir lewat Action beraudit
// (BR-RPT-09). Hapus tunggal memakai penjaga BR-FE-20 yang sama dengan hapus massal.

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
 * Baris detail baru, meminjam akses form, tipe tahap, dan tenggat milik detail pabrik.
 *
 * @return array<string, mixed>
 */
function newPhaseRow(?int $submissionDateId = null): array
{
    $reference = FormPhaseDetail::factory()->create();

    return array_filter([
        'form_access_control_id' => $reference->form_access_control_id,
        'phase_type_id' => $reference->phase_type_id,
        'order' => 1,
        'needs_review' => false,
        'submission_date_id' => $submissionDateId ?? $reference->submission_date_id,
    ], fn ($value) => $value !== null);
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function createPayload(array $rows): array
{
    return ['title' => 'Tahap Baru', 'description' => 'Uji', 'is_active' => true, 'phase_details' => $rows];
}

it('saves a created phase with the deadline of each detail (BR-FE-07)', function () {
    $row = newPhaseRow();

    $this->actingAs($this->admin)
        ->post(route('admin.form-phases.store'), createPayload([$row]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.form-phases.index'));

    $phase = FormPhase::query()->where('title', 'Tahap Baru')->firstOrFail();
    $detail = $phase->formPhaseDetails()->firstOrFail();

    expect($detail->submission_date_id)->toBe($row['submission_date_id'])
        ->and($detail->form_access_control_id)->toBe($row['form_access_control_id']);
});

it('rejects a created phase whose detail has no deadline as validation (BR-FE-07)', function () {
    $row = newPhaseRow();
    unset($row['submission_date_id']);

    $this->actingAs($this->admin)
        ->post(route('admin.form-phases.store'), createPayload([$row]))
        ->assertSessionHasErrors('phase_details.0.submission_date_id');

    expect(FormPhase::query()->where('title', 'Tahap Baru')->exists())->toBeFalse();
});

it('audits the creation of a form phase (BR-RPT-09)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.form-phases.store'), createPayload([newPhaseRow()]))
        ->assertSessionHasNoErrors();

    $phase = FormPhase::query()->where('title', 'Tahap Baru')->firstOrFail();
    $entry = auditEntry('Tahap formulir dibuat', $phase);

    expect($entry)->not->toBeNull()
        ->and($entry?->causer_id)->toBe($this->admin->id);
});

it('rejects single delete of a phase used by a submission and keeps its evaluation forms (BR-FE-20)', function () {
    $detail = FormPhaseDetail::factory()->create();
    $phase = $detail->formPhase;
    $evaluation = ReviewEvaluationForm::create([
        'title' => 'Lembar Penilaian',
        'form_phase_detail_id' => $detail->id,
        'is_required' => true,
        'is_active' => true,
        'order' => 1,
    ]);
    FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.form-phases.destroy', $phase))
        ->assertSessionHasErrors('id');

    expect(FormPhase::query()->whereKey($phase->id)->exists())->toBeTrue()
        ->and(FormPhaseDetail::query()->whereKey($detail->id)->exists())->toBeTrue()
        ->and(ReviewEvaluationForm::query()->whereKey($evaluation->id)->exists())->toBeTrue();
});

it('audits a single delete of an unused phase (BR-RPT-09)', function () {
    $detail = FormPhaseDetail::factory()->create();
    $phase = $detail->formPhase;

    $this->actingAs($this->admin)
        ->delete(route('admin.form-phases.destroy', $phase))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.form-phases.index'));

    expect(FormPhase::query()->whereKey($phase->id)->exists())->toBeFalse()
        ->and(auditEntry('Tahap formulir dihapus', $phase))->not->toBeNull();
});

it('audits a status change and answers with the same JSON keys (BR-RPT-09)', function () {
    $phase = FormPhase::create(['title' => 'Tahap Status', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->patchJson(route('admin.form-phases.update-status', $phase), ['is_active' => false])
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Status berhasil diperbarui',
            'is_active' => false,
        ]);

    $entry = auditEntry('Status tahap formulir diubah', $phase);

    expect($phase->fresh()?->is_active)->toBeFalse()
        ->and($entry)->not->toBeNull()
        ->and($entry?->causer_id)->toBe($this->admin->id);
});

it('passes deadline options labelled period, label and date to create and edit', function () {
    $detail = FormPhaseDetail::factory()->create();
    $date = $detail->submissionDate;
    $label = $date->submissionPeriod?->name.' — '.$date->submissionDateLabel?->name.' — '
        .$date->datetime->format('d/m/Y H:i');

    foreach ([route('admin.form-phases.create'), route('admin.form-phases.edit', $detail->formPhase)] as $url) {
        $this->actingAs($this->admin)->get($url)->assertInertia(
            fn ($page) => $page->where('submissionDates', fn ($options) => collect($options)
                ->contains(fn ($option) => $option['id'] === $date->id && $option['label'] === $label))
        );
    }
});

it('deletes nothing in a bulk delete when one phase is used, and audits nothing (BR-FE-20)', function () {
    $unused = FormPhaseDetail::factory()->create()->formPhase;
    $usedDetail = FormPhaseDetail::factory()->create();
    FormSubmission::factory()->create(['form_phase_detail_id' => $usedDetail->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.form-phases.bulk-delete'), ['ids' => [$unused->id, $usedDetail->form_phase_id]])
        ->assertSessionHasErrors('id');

    expect(FormPhase::query()->whereKey($unused->id)->exists())->toBeTrue()
        ->and(FormPhase::query()->whereKey($usedDetail->form_phase_id)->exists())->toBeTrue()
        ->and(auditEntry('Tahap formulir dihapus', $unused))->toBeNull();
});
