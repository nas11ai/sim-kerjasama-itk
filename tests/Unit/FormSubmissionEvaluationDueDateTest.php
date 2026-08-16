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
    ]);

    $dueDate = invokeGetEvaluationDueDate($submission);

    expect($dueDate)->not->toBeNull()
        ->and($dueDate->format('Y-m-d H:i:s'))->toBe($latestDate->datetime->format('Y-m-d H:i:s'));
});
