<?php

// tests/Feature/Submission/SubmissionWizardPageTest.php
//
// Kontrak Submission/WizardPage (fe-v2-design §4.5). Wizard hanya terbuka bagi yang boleh
// menyunting pengajuannya (FormSubmissionPolicy::update); langkah di luar 1..7 tidak ada.

use App\Models\FormSubmission;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function wizardPageDraft(array $attributes = []): FormSubmission
{
    $fx = wizardFixture();

    return FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Draft::class,
        ...$attributes,
    ]);
}

it('peneliti membuka Submission/WizardPage untuk membuat', function () {
    $fx = wizardFixture();

    $this->actingAs($fx['user'])
        ->get(route('user.submissions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/WizardPage')
            ->where('step', 1)
            ->where('submission', null)
            ->has('startOptions')
            ->has('steps', 7)
            ->where('steps.0.blockedReason', null)
            ->where('steps.1.blockedReason', 'Pilih form dan periode lebih dulu.'));
});

it('tanpa submissions.create ditolak', function () {
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('user.submissions.create'))
        ->assertForbidden();
});

it('[SM-WIZARD-07] draft dibuka di langkah terakhir yang selesai', function () {
    $submission = wizardPageDraft(['last_completed_step' => 4]);

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.wizard', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/WizardPage')
            ->where('step', 4)
            ->where('submission.id', $submission->id)
            ->where('startOptions', [])
            ->has('can'));
});

it('pengajuan yang sudah disubmit ditolak', function () {
    $submission = wizardPageDraft(['status' => Submitted::class, 'is_submitted' => true]);

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.wizard', [$submission, 2]))
        ->assertForbidden();
});

it('pengajuan peneliti lain ditolak', function () {
    $submission = wizardPageDraft();

    $this->actingAs(verifiedResearcher())
        ->get(route('user.submissions.wizard', [$submission, 2]))
        ->assertForbidden();
});

it('[SM-ARCHIVE-01] draft terarsip ditolak', function () {
    $submission = wizardPageDraft();
    $submission->submissionPeriod->update(['is_force_closed' => true]);

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.wizard', [$submission, 2]))
        ->assertForbidden();
});

it('langkah 8 tidak ada', function () {
    $submission = wizardPageDraft();

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.wizard', [$submission, 8]))
        ->assertNotFound();
});

it('[SM-SUBMIT-13] Super Admin bukan pemilik membuka wizard tanpa can.submit', function () {
    $submission = wizardPageDraft();
    $admin = verifiedResearcher();
    $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));

    // `update` lolos lewat Gate::before; `submit` invarian (ADR 0015).
    $this->actingAs($admin)
        ->get(route('user.submissions.wizard', [$submission, 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('step', 7)
            ->where('can.update', true)
            ->where('can.submit', false));
});
