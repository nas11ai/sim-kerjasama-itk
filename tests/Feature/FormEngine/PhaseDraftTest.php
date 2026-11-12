<?php

use App\Actions\Form\PublishPhaseVersion;
use App\Actions\FormSubmission\StartSubmission;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseStructureInput;
use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\PhaseDraft;
use App\Models\PhaseType;
use App\Models\PhaseVersionPin;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Form\PhasePublishImpact;
use App\Queries\Form\PhaseVersionFor;
use App\Queries\Monev\MonevCycles;
use Database\Seeders\PermissionSeeder;

/*
 * BR-FE-33 — struktur tahap disunting sebagai draf; Terbitkan memvalidasi ulang isinya lalu
 * membentuk versi berikutnya (BR-FE-32). Semua lewat route editor, jalur yang dipakai Admin.
 */

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    [$this->researcher, $this->faculty] = actingResearcher();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->phaseType = PhaseType::create(['name' => 'Tipe Draf Tahap']);

    $this->proposal = Form::factory()->create(['title' => 'Proposal Hibah', 'form_type_id' => regularFormType()->id]);
    $this->report = Form::factory()->create(['title' => 'Laporan Monev', 'form_type_id' => regularFormType()->id]);
    $this->proposalAccess = FormAccessControl::create(['form_id' => $this->proposal->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id]);
    $this->reportAccess = FormAccessControl::create(['form_id' => $this->report->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id]);

    $this->phase = FormPhase::create(['title' => 'Hibah Internal', 'is_active' => true]);
    $this->v1 = PublishPhaseVersion::run($this->phase, new PhaseStructureInput(array_map(
        fn (?int $cycle): FormPhaseDetailInput => new FormPhaseDetailInput(
            id: null,
            formAccessControlId: $cycle === null ? $this->proposalAccess->id : $this->reportAccess->id,
            phaseTypeId: $this->phaseType->id,
            needsReview: false,
            deadlineType: DeadlineType::Submit,
            deadlineCycle: $cycle,
        ),
        [null, 1, 2],
    )), $this->admin);
});

/**
 * Baris `phase_details` kiriman editor: [aturan akses, siklus, needs_review, Form Evaluasi].
 *
 * @param  list<array{0: int, 1: int|null, 2?: bool, 3?: list<array{review_evaluation_form_id: int, is_required: bool, is_active: bool}>}>  $steps
 * @return array{phase_details: list<array<string, mixed>>}
 */
function draftSteps(array $steps): array
{
    return ['phase_details' => array_map(fn (array $step): array => [
        'form_access_control_id' => $step[0],
        'phase_type_id' => test()->phaseType->id,
        'needs_review' => $step[2] ?? false,
        'deadline_type' => 'submit',
        'deadline_cycle' => $step[1],
        'evaluation_forms' => $step[3] ?? [],
    ], $steps)];
}

/** Draf Pengajuan + Monev I–III. */
function draftWithThreeCycles(): array
{
    $t = test();

    return draftSteps([
        [$t->proposalAccess->id, null],
        [$t->reportAccess->id, 1],
        [$t->reportAccess->id, 2],
        [$t->reportAccess->id, 3],
    ]);
}

function linkedPeriod(string $name): SubmissionPeriod
{
    $period = schedulePeriod(test()->phase, $name);
    calendarDate($period, DeadlineType::Submit, now()->addWeek());

    return $period;
}

function startDraftCohort(SubmissionPeriod $period): FormSubmission
{
    $t = test();
    $step = PhaseVersionFor::run($period, $t->phase)?->formPhaseDetails()->whereNull('deadline_cycle')->firstOrFail();

    return StartSubmission::run($t->proposal->id, $period->id, $step, $t->researcher);
}

it('[FE-PHPUB-01] refuses to link a phase that was never published', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.phases.store'), ['title' => 'Hibah Baru', 'is_active' => true, ...draftSteps([[$this->proposalAccess->id, null]])])
        ->assertSessionHasNoErrors();
    $fresh = FormPhase::query()->where('title', 'Hibah Baru')->sole();
    $period = SubmissionPeriod::create(['name' => 'Periode 2026', 'is_force_closed' => false]);
    calendarDate($period, DeadlineType::Submit, now()->addMonth());

    $this->post(route('admin.phases.periods.store', $fresh), ['submission_period_id' => $period->id])
        ->assertSessionHasErrors(['form_phase_id' => 'Tahap ini belum pernah diterbitkan, jadi belum bisa ditautkan ke periode.']);

    $card = collect($this->get(route('admin.phases.index'))->viewData('page')['props']['phases'])->firstWhere('id', $fresh->id);
    expect($fresh->submissionPeriodPhases()->count())->toBe(0)
        ->and($card['linkablePeriods'])->toBe([])
        ->and($card['versionLabel'])->toBeNull()
        ->and($card['hasDraft'])->toBeTrue();
});

it('[FE-PHPUB-02] keeps new submissions on the published version while a draft is unpublished', function () {
    $period = linkedPeriod('Periode 2026');

    $this->actingAs($this->admin)
        ->put(route('admin.phases.draft.update', $this->phase), draftWithThreeCycles())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Draf disimpan.');

    $submission = startDraftCohort($period);

    expect(PhaseVersionPin::query()->sole()->form_phase_version_id)->toBe($this->v1->id)
        ->and($this->phase->versions()->count())->toBe(1)
        ->and(MonevCycles::details($submission)->map(fn (FormPhaseDetail $d): string => MonevCycles::nameOf($d))->all())
        ->toBe(['Monev I', 'Monev II']);
});

it('[FE-PHPUB-03] publishes the draft as the next version, naming cohorts that keep the old one', function () {
    startDraftCohort(linkedPeriod('Periode 2025'));
    $this->actingAs($this->admin)->put(route('admin.phases.draft.update', $this->phase), draftWithThreeCycles());

    $phase = $this->get(route('admin.phases.edit', $this->phase))->viewData('page')['props']['phase'];
    expect($phase['impact'])->toBe(['nextLabel' => 'Versi 2', 'pinnedCohorts' => 1])
        ->and($phase['hasDraft'])->toBeTrue()
        ->and($phase['versionLabel'])->toBe('Versi 1')
        ->and(collect($phase['steps'])->pluck('deadlineLabel')->all())->toBe(['Pengajuan', 'Laporan Monev I', 'Laporan Monev II', 'Laporan Monev III']);

    $this->post(route('admin.phases.publish', $this->phase))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Versi 2 diterbitkan.');

    $v2 = $this->phase->latestVersion()->firstOrFail();
    expect($v2->number)->toBe(2)
        ->and($v2->formPhaseDetails()->count())->toBe(4)
        ->and(PhaseDraft::query()->count())->toBe(0)
        ->and(PhaseVersionPin::query()->sole()->form_phase_version_id)->toBe($this->v1->id);
});

it('revalidates the draft at publish and keeps it when an attached evaluation form is gone', function () {
    $sheet = ReviewEvaluationForm::create(['title' => 'Lembar Proposal']);
    publishVersion($sheet, [['type' => 'Text', 'label' => 'Catatan']]);
    $this->actingAs($this->admin)->put(route('admin.phases.draft.update', $this->phase), draftSteps([
        [$this->proposalAccess->id, null, true, [['review_evaluation_form_id' => $sheet->id, 'is_required' => true, 'is_active' => true]]],
    ]))->assertSessionHasNoErrors();
    $sheet->delete();

    $this->post(route('admin.phases.publish', $this->phase))
        ->assertSessionHasErrors(['review_evaluation_form_id' => 'Form evaluasi ini belum pernah diterbitkan, jadi belum bisa dipasang.']);

    expect($this->phase->versions()->count())->toBe(1)
        ->and(PhaseDraft::query()->where('draftable_id', $this->phase->id)->exists())->toBeTrue();
});

it('revalidates the draft at publish when a step access rule was revoked', function () {
    [, $otherFaculty] = actingResearcher();
    $extra = FormAccessControl::create(['form_id' => $this->report->id, 'permission' => 'submissions.create', 'organization_id' => $otherFaculty->id]);
    $this->actingAs($this->admin)->put(route('admin.phases.draft.update', $this->phase), draftSteps([
        [$this->proposalAccess->id, null],
        [$extra->id, 1],
    ]))->assertSessionHasNoErrors();
    $extra->delete();

    $this->post(route('admin.phases.publish', $this->phase))
        ->assertSessionHasErrors(['phase_details.1.form_access_control_id' => 'Aturan akses langkah ini sudah dihapus. Pilih aturan akses lain lalu simpan draf.']);

    expect($this->phase->versions()->count())->toBe(1)
        ->and(PhaseDraft::query()->where('draftable_id', $this->phase->id)->exists())->toBeTrue();
});

it('rejects step errors already when the draft is saved', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.phases.draft.update', $this->phase), draftSteps([[$this->reportAccess->id, 1]]))
        ->assertSessionHasErrors('phase_details');

    expect(PhaseDraft::query()->count())->toBe(0);
});

it('refuses to publish without a draft', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.phases.publish', $this->phase))
        ->assertSessionHasErrors(['draft' => 'Tidak ada draf untuk diterbitkan.']);

    expect($this->phase->versions()->count())->toBe(1);
});

it('creates a phase with a draft and no version', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.phases.store'), [
        'title' => 'Hibah Baru',
        'description' => 'Untuk 2027',
        'is_active' => true,
        ...draftSteps([[$this->proposalAccess->id, null], [$this->reportAccess->id, 1]]),
    ]);

    $fresh = FormPhase::query()->where('title', 'Hibah Baru')->sole();
    $response->assertRedirect(route('admin.phases.edit', $fresh))
        ->assertSessionHas('success', 'Tahap dibuat. Susun langkahnya lalu terbitkan.');
    expect($fresh->versions()->count())->toBe(0)
        ->and($fresh->draft()->sole()->payload['steps'])->toHaveCount(2);

    $phase = $this->get(route('admin.phases.edit', $fresh))->viewData('page')['props']['phase'];
    expect($phase['versionLabel'])->toBeNull()
        ->and($phase['impact'])->toBe(['nextLabel' => 'Versi 1', 'pinnedCohorts' => 0])
        ->and(collect($phase['steps'])->pluck('formTitle')->all())->toBe(['Proposal Hibah', 'Laporan Monev']);
});

it('discards a draft without leaving a version', function () {
    $this->actingAs($this->admin)->put(route('admin.phases.draft.update', $this->phase), draftWithThreeCycles());

    $this->delete(route('admin.phases.draft.destroy', $this->phase))
        ->assertSessionHas('success', 'Draf dibuang.');

    $phase = $this->get(route('admin.phases.edit', $this->phase))->viewData('page')['props']['phase'];
    expect(PhaseDraft::query()->count())->toBe(0)
        ->and($this->phase->versions()->count())->toBe(1)
        ->and($phase['hasDraft'])->toBeFalse()
        ->and($phase['steps'])->toHaveCount(3);
});

it('counts cohorts that keep an older version in the publish impact', function () {
    startDraftCohort(linkedPeriod('Periode 2025'));
    startDraftCohort(linkedPeriod('Periode 2026'));
    linkedPeriod('Periode 2027');

    expect(PhasePublishImpact::run($this->phase->refresh()))->toBe(['nextLabel' => 'Versi 2', 'pinnedCohorts' => 2]);
});

it('edits title, description, and active state directly without a version', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.phases.update', $this->phase), ['title' => 'Hibah Internal 2026', 'description' => 'Baru', 'is_active' => false])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Tahap diperbarui.');

    expect($this->phase->refresh()->only(['title', 'description', 'is_active']))->toBe(['title' => 'Hibah Internal 2026', 'description' => 'Baru', 'is_active' => false])
        ->and($this->phase->versions()->count())->toBe(1)
        ->and(PhaseDraft::query()->count())->toBe(0);
});

it('hides the draft routes from users without schemes.manage', function () {
    $this->actingAs($this->researcher);

    $this->put(route('admin.phases.draft.update', $this->phase), draftWithThreeCycles())->assertForbidden();
    $this->delete(route('admin.phases.draft.destroy', $this->phase))->assertForbidden();
    $this->post(route('admin.phases.publish', $this->phase))->assertForbidden();
});
