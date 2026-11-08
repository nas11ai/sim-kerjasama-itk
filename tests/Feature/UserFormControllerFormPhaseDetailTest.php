<?php

// tests/Feature/UserFormControllerFormPhaseDetailTest.php
//
// C1 — form_submissions.form_phase_detail_id dan submission_period_id dulu
// tidak pernah ditulis dari saveDraft()/submitForm(). Sejak #25/#28,
// StartSubmission-lah yang menulis kedua kolom itu saat baris lahir di
// langkah 1 — resolveFormPhaseDetail() (dengan disambiguasi ADR 0004: form
// yang dipakai lebih dari satu FormPhaseDetail, dan lintas organisasi) tetap
// hidup di sana. Test di sini memastikan simpan wizard tidak menuliskan ulang
// atau merusak apa yang sudah ditulis StartSubmission, sehingga fixture-nya
// memanggil StartSubmission::run() langsung.

use App\Actions\FormSubmission\StartSubmission;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\User;

it('persists form_phase_detail_id and submission_period_id when saving a draft', function () {
    [$user, $organization] = actingResearcher();

    $form = Form::factory()->create();
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $formPhase = FormPhase::create(['title' => 'Fase Uji', 'is_active' => true]);

    $built = buildAccessibleFormPhaseDetail($user, $organization, $accessControl, $formPhase);

    // #28 — saveDraft() tidak lagi membuat baris; langkah 1 wizard sudah
    // menulis form_phase_detail_id dan submission_period_id saat lahir.
    $started = StartSubmission::run($form->id, $built['period']->id, $built['detail'], $user);

    $this->actingAs($user)
        ->post(route('user.submissions.wizard.save', [$started, 2]), wizardPayload($started->updated_at->toISOString(), 3, [['form_field_id' => wizardTextField($form)->id, 'value' => 'Contoh nilai']]))
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $form->id)->where('submitted_by', $user->id)->firstOrFail();

    expect($submission->form_phase_detail_id)->toBe($built['detail']->id)
        ->and($submission->submission_period_id)->toBe($built['period']->id);
});

it('resolves the correct form_phase_detail when the form is shared by two phase details', function () {
    [$user, $organization] = actingResearcher();

    $form = Form::factory()->create();
    // Satu FormAccessControl (jadi satu form_id) dipakai oleh dua FormPhase
    // berbeda — persis skenario ADR 0004 ("Laporan Monev I" & "Laporan Monev
    // II" boleh memakai Form yang sama).
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);

    $formPhaseA = FormPhase::create(['title' => 'Fase A', 'is_active' => true]);
    $formPhaseB = FormPhase::create(['title' => 'Fase B', 'is_active' => true]);

    // Kandidat yang salah — kalau resolusi jatuh balik ke ->first() lewat
    // form_id saja, ini yang akan (keliru) terpilih.
    buildAccessibleFormPhaseDetail($user, $organization, $accessControl, $formPhaseA);
    $correct = buildAccessibleFormPhaseDetail($user, $organization, $accessControl, $formPhaseB);

    // #28 — StartSubmission (bukan saveDraft()) yang menjalankan
    // resolveFormPhaseDetail() dan menulis hasilnya.
    $started = StartSubmission::run($form->id, $correct['period']->id, $correct['detail'], $user);

    $this->actingAs($user)
        ->post(route('user.submissions.wizard.save', [$started, 2]), wizardPayload($started->updated_at->toISOString(), 3, [['form_field_id' => wizardTextField($form)->id, 'value' => 'Contoh nilai']]))
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $form->id)->where('submitted_by', $user->id)->firstOrFail();

    expect($submission->form_phase_detail_id)->toBe($correct['detail']->id);
});

// M0 review — resolveFormPhaseDetail() dulu men-scope hanya lewat
// form_phase_id + form_id, padahal satu Form bisa dipakai oleh beberapa
// FormAccessControl (satu per organisasi), sehingga beberapa FormPhaseDetail
// bisa berbagi form_phase_id + form_id yang sama dan hanya berbeda
// organisasi. Kode lain di method yang sama (dekat transitionTo Approved/
// Submitted) sudah men-scope resolusinya dengan ->accessibleBy($user); kalau
// resolveFormPhaseDetail() tidak ikut di-scope sama, FK yang tersimpan bisa
// jadi baris yang berbeda dari baris yang dipakai untuk menentukan status —
// record yang secara internal tidak konsisten.
it('resolves the form_phase_detail of the user\'s own organization when the same form_phase is shared across organizations', function () {
    [$user, $ownOrganization] = actingResearcher();

    $otherOrganization = Organization::create(['name' => 'Organisasi Lain '.fake()->unique()->numerify('####-####'), 'type' => 'study_program']);

    $form = Form::factory()->create();
    $formPhase = FormPhase::create(['title' => 'Fase Bersama Lintas Organisasi', 'is_active' => true]);

    // FormAccessControl milik organisasi LAIN, dibuat lebih dulu — kandidat
    // yang salah. Kalau resolusi jatuh balik ke ->first() tanpa scope
    // organisasi, ini yang akan (keliru) terpilih karena dibuat duluan.
    $otherAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $otherOrganization->id,
    ]);
    buildAccessibleFormPhaseDetail($user, $otherOrganization, $otherAccessControl, $formPhase);

    // FormAccessControl milik organisasi user sendiri — kandidat yang benar.
    // Organization::subtreeIds() jalan ke bawah dari organisasi user, dan
    // $otherOrganization bukan induk maupun turunan $ownOrganization,
    // sehingga tidak pernah masuk subtree user.
    $ownAccessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $ownOrganization->id,
    ]);
    $own = buildAccessibleFormPhaseDetail($user, $ownOrganization, $ownAccessControl, $formPhase);

    // #28 — StartSubmission (bukan saveDraft()) yang menjalankan
    // resolveFormPhaseDetail() dan menulis hasilnya.
    $started = StartSubmission::run($form->id, $own['period']->id, $own['detail'], $user);

    $this->actingAs($user)
        ->post(route('user.submissions.wizard.save', [$started, 2]), wizardPayload($started->updated_at->toISOString(), 3, [['form_field_id' => wizardTextField($form)->id, 'value' => 'Contoh nilai']]))
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $form->id)->where('submitted_by', $user->id)->firstOrFail();

    expect($submission->form_phase_detail_id)->toBe($own['detail']->id);
});
