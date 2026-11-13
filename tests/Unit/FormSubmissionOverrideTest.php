<?php

use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('isValid returns true when active and expires_at is null', function () {
    $override = new FormSubmissionOverride;
    $override->is_active = true;
    $override->expires_at = null;

    expect($override->isValid())->toBeTrue();
});

it('isValid returns true when active and expires_at is in the future', function () {
    $override = new FormSubmissionOverride;
    $override->is_active = true;
    $override->expires_at = now()->addDay();

    expect($override->isValid())->toBeTrue();
});

it('isValid returns false when expires_at is in the past', function () {
    $override = new FormSubmissionOverride;
    $override->is_active = true;
    $override->expires_at = now()->subDay();

    expect($override->isValid())->toBeFalse();
});

it('revoke sets is_active to false', function () {
    $user = User::factory()->create();
    $form = Form::factory()->create();
    $formSubmission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
    ]);

    $phaseType = PhaseType::create(['name' => 'review']);
    $formPhase = FormPhase::create(['title' => 'Tahap 1']);
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
    $period = SubmissionPeriod::create(['name' => 'Period 1']);
    $submissionDate = new SubmissionDate;
    $submissionDate->forceFill([
        'type' => DeadlineType::Submit,
        'datetime' => now(),
        'submission_period_id' => $period->id,
    ])->save();
    $formPhaseDetail = phaseStep($formPhase, [
        'form_access_control_id' => $formAccessControl->id,
        'phase_type_id' => $phaseType->id,
        'deadline_type' => DeadlineType::Submit,
        'order' => 1,
    ]);

    $override = FormSubmissionOverride::create([
        'form_submission_id' => $formSubmission->id,
        'form_phase_detail_id' => $formPhaseDetail->id,
        'granted_by' => $user->id,
        'reason' => 'test reason',
        'is_active' => true,
        'expires_at' => null,
    ]);

    $override->revoke();

    expect($override->fresh()->is_active)->toBeFalse();
});
