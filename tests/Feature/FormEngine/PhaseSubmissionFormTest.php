<?php

// tests/Feature/FormEngine/PhaseSubmissionFormTest.php
//
// BR-FE-28 — satu tahap untuk tepat satu Form pengajuan; langkah pertamanya langkah Pengajuan,
// dan langkah Laporan Monev / Upload Luaran memakai Form lain.

use App\Actions\Form\CreateFormPhase;
use App\Actions\Form\PublishPhaseDraft;
use App\Actions\Form\SavePhaseDraft;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\FormPhaseInput;
use App\Data\Request\PhaseDraftInput;
use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Monev\MonevCycles;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->phaseTypeId = PhaseType::create(['name' => 'Tipe Pustaka Tahap'])->id;
    $this->formType = regularFormType();
});

/** Satu aturan akses Form berjudul `$title` untuk organisasi `$organisation`. */
function libraryAccess(string $title, string $organisation = 'Fakultas A'): FormAccessControl
{
    $form = Form::query()->firstWhere('title', $title)
        ?? Form::factory()->create(['title' => $title, 'form_type_id' => test()->formType->id]);

    return FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => Organization::firstOrCreate(['name' => $organisation], ['type' => 'faculty'])->id,
    ]);
}

function stepInput(FormAccessControl $access, DeadlineType $type = DeadlineType::Submit, ?int $cycle = null, ?int $id = null): FormPhaseDetailInput
{
    return new FormPhaseDetailInput($id, $access->id, test()->phaseTypeId, false, $type, $cycle);
}

/**
 * BR-FE-33 — tahap baru beserta draf langkahnya; `$publish` menerbitkannya sebagai Versi 1.
 *
 * @param  list<FormPhaseDetailInput>  $steps
 */
function createPhase(array $steps, bool $publish = false): FormPhase
{
    $phase = CreateFormPhase::run(new FormPhaseInput('Penelitian DIPA', null, true), new PhaseDraftInput($steps), test()->admin);

    if ($publish) {
        PublishPhaseDraft::run($phase, test()->admin);
    }

    return $phase;
}

/**
 * @param  list<FormPhaseDetailInput>  $steps
 * @return array<string, list<string>>
 */
function phaseErrors(array $steps): array
{
    try {
        createPhase($steps);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('[FE-PHASE-01] counts one submission form reached through two access rules', function () {
    $facultyA = libraryAccess('Pengajuan Penelitian', 'Fakultas A');
    $facultyB = libraryAccess('Pengajuan Penelitian', 'Fakultas B');
    $monev = libraryAccess('Laporan Monev');

    $phase = createPhase([
        stepInput($facultyA),
        stepInput($facultyB),
        stepInput($monev, cycle: 1),
    ], publish: true);

    $steps = $phase->formPhaseDetails()->orderBy('id')->get();
    expect($steps->pluck('order')->all())->toBe([1, 1, 2]);

    $parent = FormSubmission::factory()->create([
        'form_id' => $facultyA->form_id,
        'form_phase_detail_id' => $steps[0]->id,
    ]);
    expect(MonevCycles::details($parent)->modelKeys())->toBe([$steps[2]->id]);
});

it('[FE-PHASE-02] rejects a phase with two submission forms', function () {
    $errors = phaseErrors([
        stepInput(libraryAccess('Pengajuan Penelitian')),
        stepInput(libraryAccess('Pengajuan Pengmas')),
    ]);

    expect($errors['phase_details'][0] ?? null)->toBe(
        'Satu tahap hanya untuk satu Formulir pengajuan: Pengajuan Penelitian, Pengajuan Pengmas. Buat tahap terpisah untuk tiap Formulir.',
    )->and(FormPhase::count())->toBe(0);
});

it('[FE-PHASE-03] rejects a phase whose first step is a monev report', function () {
    $errors = phaseErrors([
        stepInput(libraryAccess('Laporan Monev'), cycle: 1),
        stepInput(libraryAccess('Pengajuan Penelitian')),
    ]);

    expect($errors['phase_details.0.deadline_type'][0] ?? null)->toBe('Langkah pertama tahap harus langkah Pengajuan.')
        ->and(FormPhase::count())->toBe(0);
});

it('[FE-PHASE-04] rejects a monev report that reuses the submission form', function (DeadlineType $type, ?int $cycle) {
    $submission = libraryAccess('Pengajuan Penelitian');

    $errors = phaseErrors([stepInput($submission), stepInput($submission, $type, $cycle)]);

    expect($errors['phase_details.1.form_access_control_id'][0] ?? null)
        ->toBe('Laporan Monev dan Unggah Luaran memakai Formulir selain Formulir pengajuan.')
        ->and(FormPhase::count())->toBe(0);
})->with([
    'Laporan Monev I' => [DeadlineType::Submit, 1],
    'Unggah Luaran' => [DeadlineType::Output, null],
]);

it('[FE-PHASE-05] keeps a used phase from gaining a second submission form', function () {
    $phase = createPhase([stepInput(libraryAccess('Pengajuan Penelitian'))], publish: true);
    $step = $phase->formPhaseDetails()->sole();
    scheduledSubmission($step, SubmissionPeriod::create(['name' => 'Periode 2026']));

    $revisions = fn (): int => Activity::inLog(AuditTrail::LOG_NAME)
        ->where('description', 'Draf tahap disimpan')->count();
    $before = $revisions();

    expect(fn () => SavePhaseDraft::run($phase, new PhaseDraftInput([
        new FormPhaseDetailInput(null, $step->form_access_control_id, $this->phaseTypeId, false, DeadlineType::Submit, null),
        stepInput(libraryAccess('Pengajuan Pengmas')),
    ]), $this->admin))->toThrow(fn (ValidationException $e) => expect($e->errors()['phase_details'][0] ?? null)
        ->toBe('Satu tahap hanya untuk satu Formulir pengajuan: Pengajuan Penelitian, Pengajuan Pengmas. Buat tahap terpisah untuk tiap Formulir.'));

    expect($phase->formPhaseDetails()->count())->toBe(1)
        ->and($phase->draft()->exists())->toBeFalse()
        ->and($revisions())->toBe($before);
});

it('[FE-PHASE-06] rejects a phase with no submission step', function () {
    $errors = phaseErrors([stepInput(libraryAccess('Laporan Monev'), cycle: 1)]);

    expect($errors['phase_details'][0] ?? null)->toBe('Tahap butuh satu langkah Pengajuan.')
        ->and($errors['phase_details.0.deadline_type'][0] ?? null)->toBe('Langkah pertama tahap harus langkah Pengajuan.')
        ->and(FormPhase::count())->toBe(0);
});
