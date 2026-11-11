<?php

// tests/Feature/FormEngine/FormPhaseRevisionTest.php
//
// BR-FE-20 — merevisi tahap formulir menyinkronkan detailnya per id. Versi lama
// menghapus semua detail lewat query massal lalu membuat ulang: form evaluasi ikut
// ter-cascade dan pengajuan kehilangan tahapnya.

use App\Enums\DeadlineType;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\PhaseType;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $this->phase = FormPhase::create(['title' => 'Tahap Lama', 'is_active' => true]);
    $this->kept = FormPhaseDetail::factory()->create(['form_phase_id' => $this->phase->id]);
    // BR-FE-28 — detail kedua berform lain, jadi langkah Laporan Monev, bukan Pengajuan kedua.
    $this->dropped = FormPhaseDetail::factory()->create([
        'form_phase_id' => $this->phase->id,
        'order' => 2,
        'deadline_cycle' => 1,
    ]);
    $this->form = $this->kept->formAccessControl?->form;
});

/** @return array<string, mixed> */
function revisionRow(FormPhaseDetail $detail): array
{
    return [
        'id' => $detail->id,
        'form_access_control_id' => $detail->form_access_control_id,
        'phase_type_id' => $detail->phase_type_id,
        'order' => $detail->order,
        'needs_review' => $detail->needs_review === true,
        'deadline_type' => $detail->deadline_type->value,
        'deadline_cycle' => $detail->deadline_cycle,
        'temp_id' => 'temp_'.$detail->id,
    ];
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function revisionPayload(array $rows, string $title = 'Tahap Baru'): array
{
    return [
        'title' => $title,
        'description' => 'Direvisi',
        'is_active' => true,
        'phase_details' => $rows,
    ];
}

function reviseFormPhase(array $payload): TestResponse
{
    return test()->actingAs(test()->admin)
        ->put(route('admin.phases.update', test()->phase), $payload);
}

it('keeps the evaluation forms of a detail the revision retains (BR-FE-20)', function () {
    // Form evaluasi hanya melekat pada detail yang direview (AssertReviewedPhaseDetail, W97).
    $this->kept->update(['needs_review' => true]);
    $evaluation = ReviewEvaluationForm::create([
        'title' => 'Lembar Penilaian',
        'form_phase_detail_id' => $this->kept->id,
        'is_required' => true,
        'is_active' => true,
        'order' => 1,
    ]);

    reviseFormPhase(revisionPayload([revisionRow($this->kept)]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.phases.index'));

    expect(ReviewEvaluationForm::query()->whereKey($evaluation->id)->exists())->toBeTrue();
});

it('leaves submissions pointing at a retained detail (BR-FE-20)', function () {
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $this->kept->id]);

    reviseFormPhase(revisionPayload([revisionRow($this->kept), revisionRow($this->dropped)]))
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->form_phase_detail_id)->toBe($this->kept->id);
});

it('keeps the step kind of a retained detail (ADR 0019)', function () {
    $this->dropped->update(['deadline_cycle' => 2]);

    reviseFormPhase(revisionPayload([revisionRow($this->kept), revisionRow($this->dropped->fresh())]))
        ->assertSessionHasNoErrors();

    $retained = FormPhaseDetail::query()->find($this->dropped->id);
    expect($retained?->deadline_type)->toBe(DeadlineType::Submit)
        ->and($retained?->deadline_cycle)->toBe(2);
});

it('rejects dropping a detail still used by a submission and changes nothing (BR-FE-20)', function () {
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $this->dropped->id]);

    reviseFormPhase(revisionPayload([revisionRow($this->kept)]))->assertSessionHasErrors('id');

    expect($this->phase->fresh()->title)->toBe('Tahap Lama')
        ->and(FormPhaseDetail::query()->whereKey($this->dropped->id)->exists())->toBeTrue()
        ->and($submission->fresh()->form_phase_detail_id)->toBe($this->dropped->id);
});

it('deletes an unused dropped detail and creates a detail sent without id', function () {
    $newRow = [
        'form_access_control_id' => $this->dropped->form_access_control_id,
        'phase_type_id' => PhaseType::create(['name' => 'Tipe Baru Revisi'])->id,
        'order' => 2,
        'needs_review' => true,
        // BR-FE-07 — detail baru wajib menyebut jenis tenggatnya (ADR 0019).
        'deadline_type' => 'submit',
        'deadline_cycle' => 1,
        'temp_id' => 'temp_new',
    ];

    reviseFormPhase(revisionPayload([revisionRow($this->kept), $newRow]))
        ->assertSessionHasNoErrors();

    $details = $this->phase->formPhaseDetails()->get();

    expect(FormPhaseDetail::query()->whereKey($this->dropped->id)->exists())->toBeFalse()
        ->and($details)->toHaveCount(2)
        ->and($details->pluck('id'))->toContain($this->kept->id)
        ->and($details->firstWhere('phase_type_id', $newRow['phase_type_id'])?->needs_review)
        ->toBeTrue();
});

it('rejects a detail id that belongs to another phase', function () {
    $foreign = FormPhaseDetail::factory()->create();
    $foreignPhaseId = $foreign->form_phase_id;

    reviseFormPhase(revisionPayload([revisionRow($this->kept), revisionRow($foreign)]))
        ->assertSessionHasErrors('phase_details');

    expect($foreign->fresh()->form_phase_id)->toBe($foreignPhaseId)
        ->and($this->phase->fresh()->title)->toBe('Tahap Lama')
        ->and($this->phase->formPhaseDetails()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->kept->id, $this->dropped->id])->sort()->values()->all());
});

it('gives details of the same form the same order, numbered by first appearance', function () {
    $keptAccess = $this->kept->formAccessControl;
    $sameForm = FormAccessControl::create([
        'form_id' => $keptAccess->form_id,
        'permission' => 'submissions.create',
        'organization_id' => $keptAccess->organization_id,
    ]);
    $sibling = array_merge(revisionRow($this->kept), [
        'form_access_control_id' => $sameForm->id,
        'order' => 9,
    ]);
    unset($sibling['id']);

    // Urutan kiriman (7, 5, 9) diabaikan; saudara berform sama berbagi urutan Form pengajuan.
    $rows = [
        array_merge(revisionRow($this->kept), ['order' => 7]),
        array_merge(revisionRow($this->dropped), ['order' => 5]),
        $sibling,
    ];

    reviseFormPhase(revisionPayload($rows))->assertSessionHasNoErrors();

    $orders = $this->phase->formPhaseDetails()->pluck('order', 'form_access_control_id');

    expect($orders[$this->kept->form_access_control_id])->toBe(1)
        ->and($orders[$this->dropped->form_access_control_id])->toBe(2)
        ->and($orders[$sameForm->id])->toBe(1);
});

it('audits the revision of a form phase (BR-RPT-09)', function () {
    reviseFormPhase(revisionPayload([revisionRow($this->kept)]))->assertSessionHasNoErrors();

    $entry = Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $this->phase->getMorphClass())
        ->where('subject_id', $this->phase->id)
        ->where('description', 'Tahap formulir direvisi')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry?->causer_id)->toBe($this->admin->id);
});

it('rejects a new detail without a deadline type as validation, not SQLSTATE (BR-FE-07)', function () {
    $newRow = array_merge(revisionRow($this->kept), ['temp_id' => 'temp_new']);
    unset($newRow['id'], $newRow['deadline_type']);

    reviseFormPhase(revisionPayload([revisionRow($this->kept), $newRow]))
        ->assertSessionHasErrors('phase_details.1.deadline_type');

    expect($this->phase->formPhaseDetails()->count())->toBe(2)
        ->and($this->phase->fresh()->title)->toBe('Tahap Lama');
});

it('rejects changing the form of a detail used by a submission (BR-FE-20)', function () {
    FormSubmission::factory()->create(['form_phase_detail_id' => $this->kept->id]);
    $formId = $this->kept->form_access_control_id;

    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['form_access_control_id' => $this->dropped->form_access_control_id]),
        revisionRow($this->dropped),
    ]))->assertSessionHasErrors('phase_details.0.form_access_control_id');

    expect($this->kept->fresh()->form_access_control_id)->toBe($formId)
        ->and($this->phase->fresh()->title)->toBe('Tahap Lama');
});

it('rejects changing the phase type of a detail used by a submission (BR-FE-20)', function () {
    FormSubmission::factory()->create(['form_phase_detail_id' => $this->kept->id]);
    $typeId = $this->kept->phase_type_id;

    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['phase_type_id' => $this->dropped->phase_type_id]),
    ]))->assertSessionHasErrors('phase_details.0.phase_type_id');

    expect($this->kept->fresh()->phase_type_id)->toBe($typeId)
        ->and(FormPhaseDetail::query()->whereKey($this->dropped->id)->exists())->toBeTrue();
});

// K-1 (pemilik 2026-11-11) — jenis langkah menentukan tanggal mana yang dibaca pengajuannya;
// mengubahnya diam-diam memindahkan tenggat semua pengajuan di langkah itu.
it('rejects changing the step kind of a detail used by a submission (BR-FE-20, K-1)', function () {
    FormSubmission::factory()->create(['form_phase_detail_id' => $this->kept->id]);

    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['deadline_type' => DeadlineType::Output->value, 'needs_review' => false]),
        revisionRow($this->dropped),
    ]))->assertSessionHasErrors('phase_details.0.deadline_type');

    expect($this->kept->fresh()->deadline_type)->toBe(DeadlineType::Submit);
});

it('rejects changing the cycle of a detail used by a submission (BR-FE-20, K-1)', function () {
    FormSubmission::factory()->create(['form_phase_detail_id' => $this->kept->id]);

    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['deadline_cycle' => 2]),
        revisionRow($this->dropped),
    ]))->assertSessionHasErrors('phase_details.0.deadline_cycle');

    expect($this->kept->fresh()->deadline_cycle)->toBeNull();
});

it('saves a new order and needs_review on a detail used by a submission (BR-FE-20)', function () {
    // BR-FE-28 — Pengajuan selalu urutan 1, jadi yang berpindah urutan langkah monevnya.
    FormSubmission::factory()->create(['form_phase_detail_id' => $this->dropped->id]);
    $this->dropped->update(['order' => 5]);

    reviseFormPhase(revisionPayload([
        revisionRow($this->kept),
        array_merge(revisionRow($this->dropped->fresh()), ['needs_review' => true]),
    ]))->assertSessionHasNoErrors();

    $used = $this->dropped->fresh();

    expect($used->order)->toBe(2)
        ->and($used->needs_review)->toBeTrue();
});

it('saves a changed form on a detail no submission uses', function () {
    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['form_access_control_id' => $this->dropped->form_access_control_id]),
    ]))->assertSessionHasNoErrors();

    expect($this->kept->fresh()->form_access_control_id)->toBe($this->dropped->form_access_control_id);
});
