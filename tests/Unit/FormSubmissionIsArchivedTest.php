<?php

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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns false for draft submission with active period', function () {
    $user = User::factory()->create();
    $form = Form::factory()->create();

    $phaseType = PhaseType::create(['name' => 'submission']);
    $formPhase = FormPhase::create(['title' => 'Test Phase']);
    $organization = Organization::create([
        'name' => 'ITK',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $formAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $period = SubmissionPeriod::create([
        'name' => 'Active Period',
        'is_force_closed' => false,
    ]);
    $label = SubmissionDateLabel::create(['name' => 'Default']);
    $submissionDate = new SubmissionDate;
    $submissionDate->forceFill([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->addDays(7),
        'submission_period_id' => $period->id,
    ])->save();

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $period->id,
        'form_phase_id' => $formPhase->id,
    ]);

    FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $formAccessControl->id,
        'phase_type_id' => $phaseType->id,
        'submission_date_id' => $submissionDate->id,
        'order' => 1,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => false,
    ]);

    expect($submission->isArchived())->toBeFalse();
    expect($submission->is_archived)->toBeFalse();
});

it('returns true for draft submission with force closed period', function () {
    $user = User::factory()->create();
    $form = Form::factory()->create();

    $phaseType = PhaseType::create(['name' => 'submission']);
    $formPhase = FormPhase::create(['title' => 'Test Phase']);
    $organization = Organization::create([
        'name' => 'ITK',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $formAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $period = SubmissionPeriod::create([
        'name' => 'Force Closed Period',
        'is_force_closed' => true,
    ]);
    $label = SubmissionDateLabel::create(['name' => 'Default']);
    $submissionDate = new SubmissionDate;
    $submissionDate->forceFill([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->addDays(7),
        'submission_period_id' => $period->id,
    ])->save();

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $period->id,
        'form_phase_id' => $formPhase->id,
    ]);

    FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $formAccessControl->id,
        'phase_type_id' => $phaseType->id,
        'submission_date_id' => $submissionDate->id,
        'order' => 1,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => false,
    ]);

    expect($submission->isArchived())->toBeTrue();
    expect($submission->is_archived)->toBeTrue();
});

it('returns true for draft submission when all submission dates are in the past', function () {
    $user = User::factory()->create();
    $form = Form::factory()->create();

    $phaseType = PhaseType::create(['name' => 'submission']);
    $formPhase = FormPhase::create(['title' => 'Test Phase']);
    $organization = Organization::create([
        'name' => 'ITK',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $formAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $period = SubmissionPeriod::create([
        'name' => 'Past Period',
        'is_force_closed' => false,
    ]);
    $label = SubmissionDateLabel::create(['name' => 'Default']);

    $date1 = new SubmissionDate;
    $date1->forceFill([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->subDays(5),
        'submission_period_id' => $period->id,
    ])->save();

    $date2 = new SubmissionDate;
    $date2->forceFill([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->subDays(3),
        'submission_period_id' => $period->id,
    ])->save();

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $period->id,
        'form_phase_id' => $formPhase->id,
    ]);

    FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $formAccessControl->id,
        'phase_type_id' => $phaseType->id,
        'submission_date_id' => $date1->id,
        'order' => 1,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => false,
    ]);

    expect($submission->isArchived())->toBeTrue();
    expect($submission->is_archived)->toBeTrue();
});

it('returns false for submitted submission regardless of period state', function () {
    $user = User::factory()->create();
    $form = Form::factory()->create();

    $phaseType = PhaseType::create(['name' => 'submission']);
    $formPhase = FormPhase::create(['title' => 'Test Phase']);
    $organization = Organization::create([
        'name' => 'ITK',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $formAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $period = SubmissionPeriod::create([
        'name' => 'Closed Submitted Period',
        'is_force_closed' => true,
    ]);
    $label = SubmissionDateLabel::create(['name' => 'Default']);
    $submissionDate = new SubmissionDate;
    $submissionDate->forceFill([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->subDays(7),
        'submission_period_id' => $period->id,
    ])->save();

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $period->id,
        'form_phase_id' => $formPhase->id,
    ]);

    FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $formAccessControl->id,
        'phase_type_id' => $phaseType->id,
        'submission_date_id' => $submissionDate->id,
        'order' => 1,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => true,
    ]);

    expect($submission->isArchived())->toBeFalse();
    expect($submission->is_archived)->toBeFalse();
});
