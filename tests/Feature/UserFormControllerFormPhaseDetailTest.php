<?php

// tests/Feature/UserFormControllerFormPhaseDetailTest.php
//
// C1 — UserFormController::saveDraft() dan submitForm() dulu memvalidasi
// form_phase_id & submission_period_id lalu membuangnya begitu saja; tidak
// ada penulis untuk form_submissions.form_phase_detail_id maupun
// submission_period_id sama sekali. Test ini memastikan kedua kolom
// tersimpan, dan bahwa resolusinya tidak ambigu ketika satu Form dipakai
// oleh lebih dari satu FormPhaseDetail (ADR 0004).

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Models\UserProfile;
use Spatie\Permission\Models\Permission;

/**
 * Bangun satu FormAccessControl yang bisa diakses $user (permission +
 * organization subtree cocok), berikut satu FormPhaseDetail di bawah
 * $formPhase yang diberikan.
 *
 * @return array{form: Form, detail: FormPhaseDetail, period: SubmissionPeriod}
 */
function buildAccessibleFormPhaseDetail(User $user, Organization $organization, FormAccessControl $accessControl, FormPhase $formPhase): array
{
    $phaseType = PhaseType::create(['name' => 'Tipe Fase '.fake()->unique()->numerify('####-####')]);
    $period = SubmissionPeriod::factory()->create();
    $dateLabel = SubmissionDateLabel::create(['name' => 'Label '.fake()->unique()->numerify('####-####')]);
    $submissionDate = SubmissionDate::create([
        'submission_date_label_id' => $dateLabel->id,
        'datetime' => now()->addWeek(),
        'submission_period_id' => $period->id,
    ]);

    $detail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'submission_date_id' => $submissionDate->id,
    ]);

    return ['form' => $accessControl->form, 'detail' => $detail, 'period' => $period];
}

function actingResearcher(): array
{
    $organization = Organization::create(['name' => 'Organisasi Uji '.fake()->unique()->numerify('####-####'), 'type' => 'study_program']);
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);

    Permission::findOrCreate('submissions.create', 'web');
    $user->givePermissionTo('submissions.create');

    return [$user, $organization];
}

function draftPayload(Form $form, SubmissionPeriod $period, FormPhase $formPhase): array
{
    $fieldType = FieldType::create(['name' => 'text '.fake()->unique()->numerify('####-####')]);
    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Judul',
        'is_required' => false,
        'field_type_id' => $fieldType->id,
        'order' => 1,
    ]);

    return [
        'form_id' => $form->id,
        'submission_period_id' => $period->id,
        'form_phase_id' => $formPhase->id,
        'responses' => [
            ['form_field_id' => $field->id, 'value' => 'Contoh nilai'],
        ],
    ];
}

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

    $this->actingAs($user)
        ->post(route('user.form-submission.save-draft'), draftPayload($form, $built['period'], $formPhase))
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

    $this->actingAs($user)
        ->post(route('user.form-submission.save-draft'), draftPayload($form, $correct['period'], $formPhaseB))
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

    $this->actingAs($user)
        ->post(route('user.form-submission.save-draft'), draftPayload($form, $own['period'], $formPhase))
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $form->id)->where('submitted_by', $user->id)->firstOrFail();

    expect($submission->form_phase_detail_id)->toBe($own['detail']->id);
});

it('fails loudly instead of writing null when the form/phase combination does not exist', function () {
    [$user, $organization] = actingResearcher();

    $form = Form::factory()->create();
    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $unrelatedPhase = FormPhase::create(['title' => 'Fase Tanpa Detail', 'is_active' => true]);
    $period = SubmissionPeriod::factory()->create();

    $this->actingAs($user)
        ->post(route('user.form-submission.save-draft'), draftPayload($form, $period, $unrelatedPhase))
        ->assertStatus(422);

    expect(FormSubmission::where('form_id', $form->id)->exists())->toBeFalse();
});
