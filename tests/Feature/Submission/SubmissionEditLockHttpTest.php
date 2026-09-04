<?php

// tests/Feature/Submission/SubmissionEditLockHttpTest.php
//
// BR-SM-05 — isi pengajuan terkunci pada status terminal. Policy sudah
// menegakkannya; berkas ini membuktikan saveDraft() benar-benar menanyakannya.

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;

it('[SM-STATE-04] isi pengajuan terkunci pada status terminal', function (string $state) {
    [$user, $organization] = actingResearcher();

    $form = Form::factory()->create();
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $formPhase = FormPhase::create(['title' => 'Fase Uji', 'is_active' => true]);
    $built = buildAccessibleFormPhaseDetail($user, $organization, $accessControl, $formPhase);

    // Baris submission dibuat langsung dengan status terminal. Status di sini
    // disetel saat pembuatan, bukan lewat transisi — DRAFT→APPROVED memang
    // transisi terlarang ([SM-STATE-02]).
    FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'submission_period_id' => $built['period']->id,
        'form_phase_detail_id' => $built['detail']->id,
        'status' => $state,
    ]);

    $this->actingAs($user)
        ->post(route('user.form-submission.save-draft'), draftPayload($form, $built['period'], $formPhase))
        ->assertStatus(403);
})->with([Approved::class, Rejected::class, Withdrawn::class]);
