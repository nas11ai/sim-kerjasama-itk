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
use Illuminate\Support\Facades\Schema;

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

// Jalur backfill periode (lewat form_phase_details.submission_date_id) tidak bisa diputar ulang
// di skema sekarang: kolom itu dihapus 2026_11_12_200000 (ADR 0019). Penjaga ambiguitasnya
// berhenti sebelum backfill, jadi tetap diuji.
it('fails hard instead of guessing when a form maps to more than one phase detail', function () {
    $form = Form::factory()->create();
    $organization = Organization::create(['name' => 'Fakultas Uji Ambigu', 'type' => 'faculty']);
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $phaseType = PhaseType::create(['name' => 'Tipe Fase Uji Ambigu']);

    // Dua FormPhaseDetail untuk form_access_control yang sama -> satu form_id
    // punya lebih dari satu kandidat form_phase_detail, jadi ambigu.
    phaseStep(FormPhase::create(['title' => 'Fase Ambigu 1', 'is_active' => true]), [
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'deadline_type' => DeadlineType::Submit,
    ]);
    phaseStep(FormPhase::create(['title' => 'Fase Ambigu 2', 'is_active' => true]), [
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 2,
        'deadline_type' => DeadlineType::Submit,
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
