<?php

// tests/Unit/FormSubmissionEvaluationDueDateTest.php
//
// getEvaluationDueDate() memakai kolom 'date' dan 'submission_dates.date' yang
// sudah dihapus oleh migration 2025_08_11_002015 (diganti 'datetime'). Test ini
// membuktikan method itu mengembalikan SubmissionDate paling akhir, bukan
// melempar QueryException seperti sebelum perbaikan.

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * getEvaluationDueDate() bersifat protected — panggil lewat reflection
 * supaya test bisa menegaskan nilai kembaliannya secara langsung.
 */
function invokeGetEvaluationDueDate(FormSubmission $submission): ?DateTime
{
    return (new ReflectionMethod($submission, 'getEvaluationDueDate'))->invoke($submission);
}

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

    $dueDate = invokeGetEvaluationDueDate($submission);

    expect($dueDate)->not->toBeNull()
        ->and($dueDate->format('Y-m-d H:i:s'))->toBe($latestDate->datetime->format('Y-m-d H:i:s'));
});

it('resolves the due date from submission_period_id, not a form_phase_id shared with another period', function () {
    // ADR 0004: submission_period_phases tidak punya unique constraint, jadi
    // satu FormPhase boleh dimiliki banyak period. Kalau getEvaluationDueDate()
    // menebak period lewat pivot itu, ia bisa mengambil period yang salah persis
    // seperti bug ->first() yang ADR ini dibuat untuk membunuhnya.
    $form = Form::factory()->create();
    $organization = Organization::create([
        'name' => 'Organisasi Uji Due Date Ambigu',
        'type' => 'faculty',
    ]);
    $formAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $phaseType = PhaseType::create(['name' => 'Tipe Fase Uji Due Date Ambigu']);
    $formPhase = FormPhase::create(['title' => 'Fase Uji Due Date Ambigu', 'is_active' => true]);

    // Period A — period eksplisit yang ditunjuk submission_period_id submission.
    $periodA = SubmissionPeriod::create(['name' => 'Periode A Uji Due Date']);
    $labelA = SubmissionDateLabel::create(['name' => 'Label A Uji Due Date']);
    $dateA = new SubmissionDate;
    $dateA->forceFill([
        'submission_date_label_id' => $labelA->id,
        'datetime' => now()->addDays(5),
        'submission_period_id' => $periodA->id,
    ])->save();

    // Period B — tidak berhubungan dengan submission ini sama sekali lewat FK,
    // tapi kebetulan berbagi form_phase_id yang sama lewat pivot
    // submission_period_phases.
    $periodB = SubmissionPeriod::create(['name' => 'Periode B Uji Due Date']);
    $labelB = SubmissionDateLabel::create(['name' => 'Label B Uji Due Date']);
    $dateB = new SubmissionDate;
    $dateB->forceFill([
        'submission_date_label_id' => $labelB->id,
        'datetime' => now()->addDays(20),
        'submission_period_id' => $periodB->id,
    ])->save();

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $periodB->id,
        'form_phase_id' => $formPhase->id,
    ]);

    $phaseDetail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $formAccessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'submission_date_id' => $dateA->id,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'form_phase_detail_id' => $phaseDetail->id,
        'submission_period_id' => $periodA->id,
    ]);

    $dueDate = invokeGetEvaluationDueDate($submission);

    expect($dueDate)->not->toBeNull()
        ->and($dueDate->format('Y-m-d H:i:s'))->toBe($dateA->datetime->format('Y-m-d H:i:s'));
});
