<?php

// tests/Unit/EvaluationDueDateTest.php
//
// Versi lama (FormSubmission::getEvaluationDueDate) memakai kolom 'date' yang
// sudah dihapus migration 2025_08_11_002015. Test ini mengunci bahwa tenggatnya
// SubmissionDate paling akhir, bukan QueryException.

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Queries\Submission\EvaluationDueDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the latest submission date as the evaluation due date instead of throwing', function () {
    $form = Form::factory()->create();
    $organization = Organization::create([
        'name' => 'Organisasi Uji Due Date',
        'type' => 'faculty',
    ]);
    $formAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $phaseType = PhaseType::create(['name' => 'Tipe Fase Uji Due Date']);
    $formPhase = FormPhase::create(['title' => 'Fase Uji Due Date', 'is_active' => true]);
    $period = SubmissionPeriod::create(['name' => 'Periode Uji Due Date']);
    $label = SubmissionDateLabel::create(['name' => 'Label Uji Due Date']);

    $earlierDate = new SubmissionDate;
    $earlierDate->forceFill([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->addDays(3),
        'submission_period_id' => $period->id,
    ])->save();

    $latestDate = new SubmissionDate;
    $latestDate->forceFill([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->addDays(10),
        'submission_period_id' => $period->id,
    ])->save();

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $period->id,
        'form_phase_id' => $formPhase->id,
    ]);

    $phaseDetail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $formAccessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'submission_date_id' => $earlierDate->id,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'form_phase_detail_id' => $phaseDetail->id,
        'submission_period_id' => $period->id,
    ]);

    $dueDate = EvaluationDueDate::run($submission);

    expect($dueDate)->not->toBeNull()
        ->and($dueDate->format('Y-m-d H:i:s'))->toBe($latestDate->datetime->format('Y-m-d H:i:s'));
});

it('resolves the due date from submission_period_id, not a form_phase_id shared with another period', function () {
    // ADR 0004: submission_period_phases tidak punya unique constraint, jadi
    // satu FormPhase boleh dimiliki banyak period. Kalau EvaluationDueDate
    // menebak period lewat pivot itu, ia bisa mengambil period yang salah persis
    // seperti bug ->first() yang ADR ini dibuat untuk membunuhnya.
    $fx = phaseSharedByTwoPeriods('Uji Due Date Ambigu');

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'submission_period_id' => $fx['periodA']->id,
    ]);

    $dueDate = EvaluationDueDate::run($submission);

    expect($dueDate)->not->toBeNull()
        ->and($dueDate->format('Y-m-d H:i:s'))->toBe($fx['dueA']->format('Y-m-d H:i:s'));
});

it('mengembalikan null saat pengajuan tidak punya periode', function () {
    $submission = FormSubmission::factory()->create(['submission_period_id' => null]);

    expect(EvaluationDueDate::run($submission))->toBeNull();
});
