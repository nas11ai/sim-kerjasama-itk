<?php

// tests/Feature/FormEngine/PhaseSubmissionFormTest.php
//
// BR-FE-28 — satu tahap untuk tepat satu Form pengajuan; langkah pertamanya langkah Pengajuan,
// dan langkah Laporan Monev / Upload Luaran memakai Form lain.

use App\Actions\Form\CreateFormPhase;
use App\Actions\Form\ReviseFormPhase;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\FormPhaseInput;
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
    $this->phaseTypeId = PhaseType::create(['name' => 'Tipe Pustaka Fase'])->id;
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

function phaseStep(FormAccessControl $access, DeadlineType $type = DeadlineType::Submit, ?int $cycle = null, ?int $id = null): FormPhaseDetailInput
{
    return new FormPhaseDetailInput($id, $access->id, test()->phaseTypeId, false, $type, $cycle);
}

/** @param  list<FormPhaseDetailInput>  $steps */
function phaseInput(array $steps): FormPhaseInput
{
    return new FormPhaseInput('Penelitian DIPA', null, true, $steps);
}

/**
 * @param  list<FormPhaseDetailInput>  $steps
 * @return array<string, list<string>>
 */
function phaseErrors(array $steps): array
{
    try {
        CreateFormPhase::run(phaseInput($steps), test()->admin);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('[FE-PHASE-01] counts one submission form reached through two access rules', function () {
    $facultyA = libraryAccess('Pengajuan Penelitian', 'Fakultas A');
    $facultyB = libraryAccess('Pengajuan Penelitian', 'Fakultas B');
    $monev = libraryAccess('Laporan Monev');

    $phase = CreateFormPhase::run(phaseInput([
        phaseStep($facultyA),
        phaseStep($facultyB),
        phaseStep($monev, cycle: 1),
    ]), $this->admin);

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
        phaseStep(libraryAccess('Pengajuan Penelitian')),
        phaseStep(libraryAccess('Pengajuan Pengmas')),
    ]);

    expect($errors['phase_details'][0] ?? null)->toBe(
        'Satu tahap hanya untuk satu Form pengajuan: Pengajuan Penelitian, Pengajuan Pengmas. Buat tahap terpisah untuk tiap Form.',
    )->and(FormPhase::count())->toBe(0);
});

it('[FE-PHASE-03] rejects a phase whose first step is a monev report', function () {
    $errors = phaseErrors([
        phaseStep(libraryAccess('Laporan Monev'), cycle: 1),
        phaseStep(libraryAccess('Pengajuan Penelitian')),
    ]);

    expect($errors['phase_details.0.deadline_type'][0] ?? null)->toBe('Langkah pertama tahap harus langkah Pengajuan.')
        ->and(FormPhase::count())->toBe(0);
});

it('[FE-PHASE-04] rejects a monev report that reuses the submission form', function (DeadlineType $type, ?int $cycle) {
    $submission = libraryAccess('Pengajuan Penelitian');

    $errors = phaseErrors([phaseStep($submission), phaseStep($submission, $type, $cycle)]);

    expect($errors['phase_details.1.form_access_control_id'][0] ?? null)
        ->toBe('Laporan Monev dan Upload Luaran memakai Form selain Form pengajuan.')
        ->and(FormPhase::count())->toBe(0);
})->with([
    'Laporan Monev I' => [DeadlineType::Submit, 1],
    'Upload Luaran' => [DeadlineType::Output, null],
]);

it('[FE-PHASE-05] keeps a used phase from gaining a second submission form', function () {
    $phase = CreateFormPhase::run(phaseInput([phaseStep(libraryAccess('Pengajuan Penelitian'))]), $this->admin);
    $step = $phase->formPhaseDetails()->sole();
    scheduledSubmission($step, SubmissionPeriod::create(['name' => 'Periode 2026']));

    $revisions = fn (): int => Activity::inLog(AuditTrail::LOG_NAME)
        ->where('description', 'Tahap formulir direvisi')->count();

    expect(fn () => ReviseFormPhase::run($phase, phaseInput([
        new FormPhaseDetailInput($step->id, $step->form_access_control_id, $this->phaseTypeId, false, DeadlineType::Submit, null),
        phaseStep(libraryAccess('Pengajuan Pengmas')),
    ]), $this->admin))->toThrow(fn (ValidationException $e) => expect($e->errors()['phase_details'][0] ?? null)
        ->toBe('Satu tahap hanya untuk satu Form pengajuan: Pengajuan Penelitian, Pengajuan Pengmas. Buat tahap terpisah untuk tiap Form.'));

    expect($phase->formPhaseDetails()->count())->toBe(1)
        ->and($revisions())->toBe(0);
});

it('[FE-PHASE-06] rejects a phase with no submission step', function () {
    $errors = phaseErrors([phaseStep(libraryAccess('Laporan Monev'), cycle: 1)]);

    expect($errors['phase_details'][0] ?? null)->toBe('Tahap butuh satu langkah Pengajuan.')
        ->and($errors['phase_details.0.deadline_type'][0] ?? null)->toBe('Langkah pertama tahap harus langkah Pengajuan.')
        ->and(FormPhase::count())->toBe(0);
});
