<?php

// tests/Feature/FormEngine/FormPhaseRevisionTest.php
//
// BR-FE-20 — merevisi tahap formulir menyinkronkan detailnya per id. Versi lama
// menghapus semua detail lewat query massal lalu membuat ulang: form evaluasi ikut
// ter-cascade, pengajuan kehilangan tahapnya, dan tautan tenggatnya hilang.

use App\Enums\DeadlineType;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\PhaseType;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionDate;
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
    $this->dropped = FormPhaseDetail::factory()->create([
        'form_phase_id' => $this->phase->id,
        'order' => 2,
    ]);
});

/** BR-FE-26 — Batas Evaluasi di periode Batas Submit detail itu, untuk baris yang direview. */
function revisionEvaluationDate(FormPhaseDetail $detail): int
{
    return SubmissionDate::create([
        'type' => DeadlineType::Evaluation,
        'datetime' => now()->addMonth(),
        'submission_period_id' => $detail->submissionDate->submission_period_id,
    ])->id;
}

/** @return array<string, mixed> */
function revisionRow(FormPhaseDetail $detail): array
{
    return [
        'id' => $detail->id,
        'form_access_control_id' => $detail->form_access_control_id,
        'phase_type_id' => $detail->phase_type_id,
        'order' => $detail->order,
        'needs_review' => $detail->needs_review === true,
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
        ->put(route('admin.form-phases.update', test()->phase), $payload);
}

it('keeps the evaluation forms of a detail the revision retains (BR-FE-20)', function () {
    $evaluation = ReviewEvaluationForm::create([
        'title' => 'Lembar Penilaian',
        'form_phase_detail_id' => $this->kept->id,
        'is_required' => true,
        'is_active' => true,
        'order' => 1,
    ]);

    reviseFormPhase(revisionPayload([revisionRow($this->kept)]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.form-phases.index'));

    expect(ReviewEvaluationForm::query()->whereKey($evaluation->id)->exists())->toBeTrue();
});

it('leaves submissions pointing at a retained detail (BR-FE-20)', function () {
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $this->kept->id]);

    reviseFormPhase(revisionPayload([revisionRow($this->kept), revisionRow($this->dropped)]))
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->form_phase_detail_id)->toBe($this->kept->id);
});

it('keeps the submission_date_id of a retained detail', function () {
    $dateId = $this->kept->submission_date_id;

    reviseFormPhase(revisionPayload([revisionRow($this->kept)]))->assertSessionHasNoErrors();

    expect(FormPhaseDetail::query()->find($this->kept->id)?->submission_date_id)->toBe($dateId);
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
        // BR-FE-07 — detail baru wajib membawa tenggatnya sendiri.
        'submission_date_id' => $this->dropped->submission_date_id,
        'evaluation_date_id' => revisionEvaluationDate($this->dropped),
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
        'submission_date_id' => $this->kept->submission_date_id,
    ]);
    unset($sibling['id']);

    $rows = [
        array_merge(revisionRow($this->dropped), ['order' => 5]),
        array_merge(revisionRow($this->kept), ['order' => 7]),
        $sibling,
    ];

    reviseFormPhase(revisionPayload($rows))->assertSessionHasNoErrors();

    $orders = $this->phase->formPhaseDetails()->pluck('order', 'form_access_control_id');

    expect($orders[$this->dropped->form_access_control_id])->toBe(1)
        ->and($orders[$this->kept->form_access_control_id])->toBe(2)
        ->and($orders[$sameForm->id])->toBe(2);
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

it('rejects a new detail without a deadline as validation, not SQLSTATE (BR-FE-07)', function () {
    $newRow = array_merge(revisionRow($this->kept), ['temp_id' => 'temp_new']);
    unset($newRow['id']);

    reviseFormPhase(revisionPayload([revisionRow($this->kept), $newRow]))
        ->assertSessionHasErrors('phase_details.1.submission_date_id');

    expect($this->phase->formPhaseDetails()->count())->toBe(2)
        ->and($this->phase->fresh()->title)->toBe('Tahap Lama');
});

it('saves an existing detail moved to another deadline (BR-FE-07)', function () {
    $other = FormPhaseDetail::factory()->create()->submission_date_id;

    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['submission_date_id' => $other]),
    ]))->assertSessionHasNoErrors();

    expect(FormPhaseDetail::query()->find($this->kept->id)?->submission_date_id)->toBe($other);
});

it('keeps the deadline of an existing detail sent with null (BR-FE-07)', function () {
    $dateId = $this->kept->submission_date_id;

    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['submission_date_id' => null]),
    ]))->assertSessionHasNoErrors();

    expect(FormPhaseDetail::query()->find($this->kept->id)?->submission_date_id)->toBe($dateId);
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

it('saves a new order and needs_review on a detail used by a submission (BR-FE-20)', function () {
    FormSubmission::factory()->create(['form_phase_detail_id' => $this->kept->id]);

    reviseFormPhase(revisionPayload([
        revisionRow($this->dropped),
        array_merge(revisionRow($this->kept), ['needs_review' => true, 'evaluation_date_id' => revisionEvaluationDate($this->kept)]),
    ]))->assertSessionHasNoErrors();

    $kept = $this->kept->fresh();

    expect($kept->order)->toBe(2)
        ->and($kept->needs_review)->toBeTrue();
});

it('saves a changed form on a detail no submission uses', function () {
    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['form_access_control_id' => $this->dropped->form_access_control_id]),
    ]))->assertSessionHasNoErrors();

    expect($this->kept->fresh()->form_access_control_id)->toBe($this->dropped->form_access_control_id);
});
