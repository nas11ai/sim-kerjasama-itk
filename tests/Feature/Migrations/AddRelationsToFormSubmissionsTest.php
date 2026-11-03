<?php

// tests/Feature/Migrations/AddRelationsToFormSubmissionsTest.php

use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use Illuminate\Support\Facades\Schema;

function createSubmissionDate(int $periodId): int
{
    return SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => now(),
        'submission_period_id' => $periodId,
    ])->id;
}

// tests/Pest.php sudah menerapkan TestCase + RefreshDatabase ke seluruh
// folder Feature, jadi tidak perlu memanggil uses() lagi di sini.

/**
 * Panggil private method pada instance migration lewat reflection.
 * RefreshDatabase sudah menjalankan up() penuh sekali (kolomnya sudah ada),
 * jadi jalur guard/backfill diuji ulang secara terisolasi tanpa mengulang
 * Schema::table() yang akan gagal karena kolom sudah ada.
 */
function invokeMigrationMethod(object $migration, string $method): void
{
    (new ReflectionMethod($migration, $method))->invoke($migration);
}

it('adds relation columns to form_submissions', function () {
    expect(Schema::hasColumn('form_submissions', 'form_phase_detail_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'submission_period_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'scheme_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'trl_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'last_completed_step'))->toBeTrue();
});

it('defaults last_completed_step to zero', function () {
    $submission = FormSubmission::factory()->create();

    expect($submission->fresh()->last_completed_step)->toBe(0);
});

it('backfills form_phase_detail_id and submission_period_id when the mapping is unambiguous', function () {
    $form = Form::factory()->create();
    $organization = Organization::create(['name' => 'Fakultas Uji Backfill', 'type' => 'faculty']);
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $phaseType = PhaseType::create(['name' => 'Tipe Fase Uji Backfill']);
    $formPhase = FormPhase::create(['title' => 'Fase Uji Backfill', 'is_active' => true]);
    $period = SubmissionPeriod::create(['name' => 'Periode Uji Backfill']);
    $submissionDateId = createSubmissionDate($period->id);
    $phaseDetail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'submission_date_id' => $submissionDateId,
    ]);

    $submission = FormSubmission::factory()->create(['form_id' => $form->id]);

    $migration = require database_path('migrations/2026_08_17_000001_add_relations_to_form_submissions.php');

    invokeMigrationMethod($migration, 'guardAgainstAmbiguousPhaseDetails');
    invokeMigrationMethod($migration, 'backfillPhaseDetail');
    invokeMigrationMethod($migration, 'backfillPeriod');

    $freshSubmission = $submission->fresh();

    expect($freshSubmission->form_phase_detail_id)->toBe($phaseDetail->id)
        ->and($freshSubmission->submission_period_id)->toBe($period->id);
});

it('fails hard instead of guessing when a form maps to more than one phase detail', function () {
    $form = Form::factory()->create();
    $organization = Organization::create(['name' => 'Fakultas Uji Ambigu', 'type' => 'faculty']);
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $phaseType = PhaseType::create(['name' => 'Tipe Fase Uji Ambigu']);
    $period = SubmissionPeriod::create(['name' => 'Periode Uji Ambigu']);
    $submissionDateId = createSubmissionDate($period->id);

    // Dua FormPhaseDetail untuk form_access_control yang sama -> satu form_id
    // punya lebih dari satu kandidat form_phase_detail, jadi ambigu.
    FormPhaseDetail::create([
        'form_phase_id' => FormPhase::create(['title' => 'Fase Ambigu 1', 'is_active' => true])->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'submission_date_id' => $submissionDateId,
    ]);
    FormPhaseDetail::create([
        'form_phase_id' => FormPhase::create(['title' => 'Fase Ambigu 2', 'is_active' => true])->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 2,
        'submission_date_id' => $submissionDateId,
    ]);

    $submission = FormSubmission::factory()->create(['form_id' => $form->id]);

    $migration = require database_path('migrations/2026_08_17_000001_add_relations_to_form_submissions.php');

    $runBackfill = function () use ($migration) {
        invokeMigrationMethod($migration, 'guardAgainstAmbiguousPhaseDetails');
        invokeMigrationMethod($migration, 'backfillPhaseDetail');
        invokeMigrationMethod($migration, 'backfillPeriod');
    };

    expect($runBackfill)->toThrow(RuntimeException::class, (string) $submission->id);

    $freshSubmission = $submission->fresh();

    expect($freshSubmission->form_phase_detail_id)->toBeNull()
        ->and($freshSubmission->submission_period_id)->toBeNull();
});
