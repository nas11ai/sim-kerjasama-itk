<?php

// tests/Feature/FormEngine/FormPhaseRevisionTest.php
//
// BR-FE-33 — merevisi struktur tahap = simpan draf lalu Terbitkan, membentuk versi berikutnya
// (BR-FE-32). Versi 1 tidak berubah, jadi pengajuan dan penugasannya tetap pada langkah yang
// persis sama (BR-FE-20). Draf tanpa id langkah: setiap terbit menyalin baris baru.

use App\Enums\DeadlineType;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
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
        'form_access_control_id' => $detail->form_access_control_id,
        'phase_type_id' => $detail->phase_type_id,
        'order' => $detail->order,
        'needs_review' => $detail->needs_review === true,
        'deadline_type' => $detail->deadline_type->value,
        'deadline_cycle' => $detail->deadline_cycle,
    ];
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function revisionPayload(array $rows): array
{
    return ['phase_details' => $rows];
}

/** Simpan draf lalu Terbitkan; respons Terbitkan, atau respons simpan bila draf ditolak. */
function reviseFormPhase(array $payload): TestResponse
{
    $saved = test()->actingAs(test()->admin)->put(route('admin.phases.draft.update', test()->phase), $payload);

    if (session()->has('errors')) {
        return $saved;
    }

    return test()->post(route('admin.phases.publish', test()->phase));
}

/** @return list<int> */
function currentStepIds(): array
{
    return test()->phase->currentDetails()->pluck('id')->all();
}

it('publishes the evaluation forms of a draft step into the new version', function () {
    // Form evaluasi hanya melekat pada detail yang direview (PublishPhaseVersion, W97).
    $this->kept = restep($this->kept, ['needs_review' => true]);
    $evaluation = evaluationFormOn($this->kept, [], title: 'Lembar Penilaian');
    $row = [...revisionRow($this->kept), 'evaluation_forms' => [
        ['review_evaluation_form_id' => $evaluation->id, 'is_required' => true, 'is_active' => true],
    ]];

    reviseFormPhase(revisionPayload([$row]))->assertSessionHasNoErrors();

    $copy = $this->phase->currentDetails()->sole();
    expect($copy->id)->not->toBe($this->kept->id)
        ->and($copy->stepEvaluationForms()->pluck('review_evaluation_form_id')->all())->toBe([$evaluation->id])
        ->and($this->kept->stepEvaluationForms()->count())->toBe(1);
});

it('leaves submissions on the step of the version they started in', function () {
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $this->kept->id]);

    reviseFormPhase(revisionPayload([revisionRow($this->kept), revisionRow($this->dropped)]))
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->form_phase_detail_id)->toBe($this->kept->id)
        ->and(currentStepIds())->not->toContain($this->kept->id)
        ->and($this->phase->latestVersion()->firstOrFail()->number)->toBe(2);
});

it('publishes the step kind of each draft step (ADR 0019)', function () {
    reviseFormPhase(revisionPayload([revisionRow($this->kept), array_merge(revisionRow($this->dropped), ['deadline_cycle' => 2])]))
        ->assertSessionHasNoErrors();

    $copy = $this->phase->currentDetails()->get()->last();
    expect($copy?->deadline_type)->toBe(DeadlineType::Submit)
        ->and($copy?->deadline_cycle)->toBe(2)
        ->and($this->dropped->fresh()?->deadline_cycle)->toBe(1);
});

it('drops a step used by a submission only from the new version (BR-FE-20, BR-FE-32)', function () {
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $this->dropped->id]);

    reviseFormPhase(revisionPayload([revisionRow($this->kept)]))->assertSessionHasNoErrors();

    expect(currentStepIds())->toHaveCount(1)
        ->and(FormPhaseDetail::query()->whereKey($this->dropped->id)->exists())->toBeTrue()
        ->and($submission->fresh()->form_phase_detail_id)->toBe($this->dropped->id);
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

    // Urutan kiriman (7, 5, 9) diabaikan; saudara berform sama berbagi urutan Form pengajuan.
    $rows = [
        array_merge(revisionRow($this->kept), ['order' => 7]),
        array_merge(revisionRow($this->dropped), ['order' => 5]),
        $sibling,
    ];

    reviseFormPhase(revisionPayload($rows))->assertSessionHasNoErrors();

    $orders = $this->phase->currentDetails()->pluck('order', 'form_access_control_id');

    expect($orders[$this->kept->form_access_control_id])->toBe(1)
        ->and($orders[$this->dropped->form_access_control_id])->toBe(2)
        ->and($orders[$sameForm->id])->toBe(1);
});

it('audits the revision of a form phase (BR-RPT-09)', function () {
    reviseFormPhase(revisionPayload([revisionRow($this->kept)]))->assertSessionHasNoErrors();

    $entry = Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $this->phase->getMorphClass())
        ->where('subject_id', $this->phase->id)
        ->where('description', 'Draf tahap diterbitkan')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry?->causer_id)->toBe($this->admin->id);
});

it('rejects a new detail without a deadline type as validation, not SQLSTATE (BR-FE-07)', function () {
    $newRow = revisionRow($this->kept);
    unset($newRow['deadline_type']);

    reviseFormPhase(revisionPayload([revisionRow($this->kept), $newRow]))
        ->assertSessionHasErrors('phase_details.1.deadline_type');

    expect($this->phase->formPhaseDetails()->count())->toBe(2)
        ->and($this->phase->draft()->exists())->toBeFalse();
});

// K-1 diganti versi beku (G3-W5): mengubah langkah yang sudah dipakai hanya berlaku di versi baru,
// jadi tenggat pengajuan lama tidak pernah berpindah diam-diam.
it('changes a step used by a submission only in the new version (BR-FE-32)', function () {
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $this->dropped->id]);
    $before = $this->dropped->fresh()?->only(['deadline_cycle', 'needs_review', 'phase_type_id']);

    reviseFormPhase(revisionPayload([
        revisionRow($this->kept),
        array_merge(revisionRow($this->dropped), ['deadline_cycle' => 2, 'needs_review' => true, 'phase_type_id' => $this->kept->phase_type_id]),
    ]))->assertSessionHasNoErrors();

    $copy = $this->phase->currentDetails()->get()->last();
    expect($copy?->deadline_cycle)->toBe(2)
        ->and($copy?->needs_review)->toBeTrue()
        ->and($copy?->phase_type_id)->toBe($this->kept->phase_type_id)
        ->and($this->dropped->fresh()?->only(['deadline_cycle', 'needs_review', 'phase_type_id']))->toBe($before)
        ->and($submission->fresh()->form_phase_detail_id)->toBe($this->dropped->id);
});

it('saves a changed form on a step', function () {
    reviseFormPhase(revisionPayload([
        array_merge(revisionRow($this->kept), ['form_access_control_id' => $this->dropped->form_access_control_id]),
    ]))->assertSessionHasNoErrors();

    expect($this->phase->currentDetails()->sole()->form_access_control_id)->toBe($this->dropped->form_access_control_id)
        ->and($this->kept->fresh()?->form_access_control_id)->toBe($this->kept->form_access_control_id);
});
