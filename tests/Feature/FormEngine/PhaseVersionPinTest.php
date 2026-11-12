<?php

use App\Actions\Form\PinPhaseVersion;
use App\Actions\Form\PublishPhaseVersion;
use App\Actions\FormSubmission\StartSubmission;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseStructureInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormPhaseVersion;
use App\Models\FormSubmission;
use App\Models\PhaseType;
use App\Models\PhaseVersionPin;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Form\PhaseVersionFor;
use App\Queries\Monev\MonevCycles;
use App\Queries\Submission\AccessibleFormPhaseDetail;
use App\Queries\Submission\ResearcherStartOptions;
use Illuminate\Validation\ValidationException;

/*
 * BR-FE-32 — struktur tahap berversi, dipaku per angkatan (Periode × Tahap) saat pengajuan
 * pertamanya dibuat. Langkah dibangun lewat PublishPhaseVersion, jalur yang dipakai editor.
 */

beforeEach(function () {
    [$this->researcher, $this->faculty] = actingResearcher();
    $this->admin = User::factory()->create();
    $this->phaseType = PhaseType::create(['name' => 'Tipe Versi Struktur']);

    $this->proposal = Form::factory()->create(['title' => 'Proposal Hibah', 'form_type_id' => regularFormType()->id]);
    $this->report = Form::factory()->create(['title' => 'Laporan Monev', 'form_type_id' => regularFormType()->id]);
    $this->proposalAccess = FormAccessControl::create(['form_id' => $this->proposal->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id]);
    $this->reportAccess = FormAccessControl::create(['form_id' => $this->report->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id]);

    $this->phase = FormPhase::create(['title' => 'Hibah Internal', 'is_active' => true]);
    $this->v1 = PublishPhaseVersion::run($this->phase, structureOf([
        [$this->proposalAccess->id, null],
        [$this->reportAccess->id, 1],
        [$this->reportAccess->id, 2],
    ], $this->phaseType), $this->admin);

    $this->p2025 = schedulePeriod($this->phase, 'Periode 2025');
    calendarDate($this->p2025, DeadlineType::Submit, now()->addWeek());
});

/**
 * @param  list<array{0: int, 1: int|null, 2?: bool, 3?: list<StepEvaluationFormInput>}>  $steps  [aturan akses, siklus, needs_review, Form Evaluasi]
 */
function structureOf(array $steps, PhaseType $type): PhaseStructureInput
{
    return new PhaseStructureInput(array_map(fn (array $step): FormPhaseDetailInput => new FormPhaseDetailInput(
        id: null,
        formAccessControlId: $step[0],
        phaseTypeId: $type->id,
        needsReview: $step[2] ?? false,
        deadlineType: DeadlineType::Submit,
        deadlineCycle: $step[1],
        evaluationForms: $step[3] ?? [],
    ), $steps));
}

/** Versi 2: Pengajuan + Monev I–III. */
function publishThreeCycles(object $t): FormPhaseVersion
{
    return PublishPhaseVersion::run($t->phase, structureOf([
        [$t->proposalAccess->id, null],
        [$t->reportAccess->id, 1],
        [$t->reportAccess->id, 2],
        [$t->reportAccess->id, 3],
    ], $t->phaseType), $t->admin);
}

function submissionStepOf(FormPhaseVersion $version): FormPhaseDetail
{
    return $version->formPhaseDetails()->where('deadline_cycle', null)->firstOrFail();
}

function startIn(object $t, SubmissionPeriod $period, ?User $user = null): FormSubmission
{
    $detail = submissionStepOf(PhaseVersionFor::run($period, $t->phase));

    return StartSubmission::run($t->proposal->id, $period->id, $detail, $user ?? $t->researcher);
}

/** @return list<string> */
function cycleNames(FormSubmission $parent): array
{
    return MonevCycles::details($parent)->map(fn (FormPhaseDetail $d): string => MonevCycles::nameOf($d))->all();
}

it('[FE-PHVER-01] pins the latest published version when a cohort gets its first submission', function () {
    $submission = startIn($this, $this->p2025);

    $pin = PhaseVersionPin::query()->sole();

    expect($pin->submission_period_id)->toBe($this->p2025->id)
        ->and($pin->form_phase_id)->toBe($this->phase->id)
        ->and($pin->form_phase_version_id)->toBe($this->v1->id)
        ->and($submission->form_phase_detail_id)->toBe(submissionStepOf($this->v1)->id)
        ->and($this->v1->label())->toBe('Versi 1');
});

it('[FE-PHVER-02] lets a cohort without submissions follow the latest version', function () {
    $v2 = publishThreeCycles($this);

    $submission = startIn($this, $this->p2025);

    expect(PhaseVersionPin::query()->sole()->form_phase_version_id)->toBe($v2->id)
        ->and($v2->label())->toBe('Versi 2')
        ->and(cycleNames($submission))->toBe(['Monev I', 'Monev II', 'Monev III']);
});

it('[FE-PHVER-03] keeps the monev cycles of a pinned cohort when a new version adds a cycle', function () {
    $parent = startIn($this, $this->p2025);
    publishThreeCycles($this);

    expect(cycleNames($parent->refresh()))->toBe(['Monev I', 'Monev II'])
        ->and(MonevCycles::whereHasCycles(FormSubmission::query())->whereKey($parent->id)->exists())->toBeTrue();

    // Langkah Monev III Versi 2 bukan siklus induk Versi 1: whereHasCycles membaca versi induk.
    $v1Last = $this->v1->formPhaseDetails()->where('deadline_cycle', 2)->sole();
    $onlyLast = FormSubmission::factory()->create([
        'form_id' => $this->report->id,
        'form_phase_detail_id' => $v1Last->id,
        'submission_period_id' => $this->p2025->id,
    ]);
    expect(MonevCycles::whereHasCycles(FormSubmission::query())->whereKey($onlyLast->id)->exists())->toBeFalse();
});

it('[FE-PHVER-04] lets another cohort of the same phase use the latest version', function () {
    startIn($this, $this->p2025);
    $p2026 = schedulePeriod($this->phase, 'Periode 2026');
    calendarDate($p2026, DeadlineType::Submit, now()->addWeek());
    $v2 = publishThreeCycles($this);

    $submission = startIn($this, $p2026);

    $pins = PhaseVersionPin::query()->pluck('form_phase_version_id', 'submission_period_id');
    expect($pins->all())->toBe([$this->p2025->id => $this->v1->id, $p2026->id => $v2->id])
        ->and(cycleNames($submission))->toBe(['Monev I', 'Monev II', 'Monev III']);
});

it('[FE-PHVER-05] refuses to swap the submission form of a phase a cohort is pinned to', function () {
    startIn($this, $this->p2025);
    $other = Form::factory()->create(['title' => 'Proposal Lain', 'form_type_id' => regularFormType()->id]);
    $otherAccess = FormAccessControl::create(['form_id' => $other->id, 'permission' => 'submissions.create', 'organization_id' => $this->faculty->id]);

    expect(fn () => PublishPhaseVersion::run($this->phase, structureOf([
        [$otherAccess->id, null],
        [$this->reportAccess->id, 1],
    ], $this->phaseType), $this->admin))->toThrow(function (ValidationException $e) {
        expect($e->errors())->toBe(['phase_details' => ['Form pengajuan tahap ini sudah dipakai angkatan berjalan, jadi tidak bisa diganti. Buat tahap baru untuk Form lain.']]);
    });

    expect($this->phase->versions()->count())->toBe(1);
});

it('rejects a step from a version older than the new pin', function () {
    $staleStep = submissionStepOf($this->v1);
    $v2 = publishThreeCycles($this);

    expect(fn () => StartSubmission::run($this->proposal->id, $this->p2025->id, $staleStep, $this->researcher))
        ->toThrow(function (ValidationException $e) {
            expect($e->errors())->toBe(['form_phase_detail_id' => ['Struktur tahap ini baru saja diterbitkan ulang. Muat ulang halaman lalu mulai lagi.']]);
        });

    expect(FormSubmission::query()->count())->toBe(0)
        ->and(PhaseVersionPin::query()->count())->toBe(0);

    startIn($this, $this->p2025);
    expect(PhaseVersionPin::query()->sole()->form_phase_version_id)->toBe($v2->id);
});

it('returns the same version to two first submissions of one cohort', function () {
    $step = submissionStepOf($this->v1);

    $first = PinPhaseVersion::run($this->p2025, $step);
    publishThreeCycles($this);
    $second = PinPhaseVersion::run($this->p2025, $step);

    expect($first->id)->toBe($this->v1->id)
        ->and($second->id)->toBe($this->v1->id)
        ->and(PhaseVersionPin::query()->count())->toBe(1);
});

it('refuses to update a published step', function () {
    $step = submissionStepOf($this->v1);

    expect(fn () => $step->update(['order' => 9]))->toThrow(LogicException::class)
        ->and(fn () => $this->v1->update(['number' => 9]))->toThrow(LogicException::class);
});

it('keeps a step evaluation form on the version that was assigned', function () {
    $form = ReviewEvaluationForm::create(['title' => 'Lembar Proposal']);
    publishVersion($form, [['type' => 'Text', 'label' => 'Catatan']]);
    $v2 = PublishPhaseVersion::run($this->phase, structureOf([
        [$this->proposalAccess->id, null, true, [new StepEvaluationFormInput($form->id, isRequired: true, isActive: true)]],
        [$this->reportAccess->id, 1],
    ], $this->phaseType), $this->admin);
    // BR-FE-36 — langkah Pengajuan yang direview butuh Batas Evaluasi supaya angkatannya siap.
    calendarDate($this->p2025, DeadlineType::Evaluation, now()->addWeeks(2));
    $submission = startIn($this, $this->p2025);
    $attached = submissionStepOf($v2)->stepEvaluationForms()->sole();
    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => reviewerOn($submission, 'pending')->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
    ]);

    // BR-FE-30 — melepas = versi berikutnya tanpa pemasangan itu.
    PublishPhaseVersion::run($this->phase, structureOf([
        [$this->proposalAccess->id, null, true],
        [$this->reportAccess->id, 1],
    ], $this->phaseType), $this->admin);

    $v3 = $this->phase->latestVersion()->firstOrFail();
    expect($v3->number)->toBe(3)
        ->and(submissionStepOf($v3)->stepEvaluationForms()->count())->toBe(0)
        ->and(StepEvaluationForm::query()->whereKey($attached->id)->exists())->toBeTrue()
        ->and($assignment->fresh())->not->toBeNull();
});

it('offers the pinned version step to a researcher in a pinned cohort', function () {
    startIn($this, $this->p2025);
    $p2026 = schedulePeriod($this->phase, 'Periode 2026');
    calendarDate($p2026, DeadlineType::Submit, now()->addWeek());

    // Versi 2 membuka Form pengajuan yang sama untuk fakultas lain.
    [$newcomer, $otherFaculty] = actingResearcher();
    $otherAccess = FormAccessControl::create(['form_id' => $this->proposal->id, 'permission' => 'submissions.create', 'organization_id' => $otherFaculty->id]);
    $v2 = PublishPhaseVersion::run($this->phase, structureOf([
        [$this->proposalAccess->id, null],
        [$otherAccess->id, null],
        [$this->reportAccess->id, 1],
    ], $this->phaseType), $this->admin);

    $offered = collect(ResearcherStartOptions::run($newcomer))->pluck('id')->all();

    expect($offered)->toBe([$p2026->id])
        ->and(AccessibleFormPhaseDetail::run($this->phase->id, $this->proposal->id, $this->p2025->id, $newcomer))->toBeNull()
        ->and(AccessibleFormPhaseDetail::run($this->phase->id, $this->proposal->id, $p2026->id, $newcomer)?->form_phase_version_id)->toBe($v2->id)
        ->and(AccessibleFormPhaseDetail::run($this->phase->id, $this->proposal->id, $this->p2025->id, $this->researcher)?->id)->toBe(submissionStepOf($this->v1)->id);
});
