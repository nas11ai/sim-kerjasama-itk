<?php

// tests/Support/fixtures.php
//
// Fixture yang dipakai lintas berkas test. Sebelumnya tinggal di dalam
// berkas test tertentu, jadi hanya terbaca kalau berkas itu kebetulan ikut
// dimuat — test yang dijalankan sendirian langsung pecah. Di sini ia selalu
// tersedia, dan tempatnya jelas saat ada yang perlu menambah.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Models\UserProfile;
use Spatie\Permission\Models\Permission;

/**
 * Satu FormPhaseDetail yang benar-benar bisa diakses $user, lengkap dengan
 * periode dan tanggalnya.
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

/**
 * Peneliti beserta organisasinya, sudah memegang permission submissions.create.
 *
 * @return array{0: User, 1: Organization}
 */
function actingResearcher(): array
{
    $organization = Organization::create([
        'name' => 'Organisasi Uji '.fake()->unique()->numerify('####-####'),
        'type' => 'study_program',
    ]);

    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);

    Permission::findOrCreate('submissions.create', 'web');
    $user->givePermissionTo('submissions.create');

    return [$user, $organization];
}

/**
 * Form yang bisa diakses peneliti, siap dipakai wizard langkah 1.
 *
 * @return array{user: User, form: Form, period: SubmissionPeriod, detail: FormPhaseDetail, phase: FormPhase}
 */
function wizardFixture(): array
{
    [$user, $organization] = actingResearcher();

    $form = Form::factory()->create();

    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);

    $formPhase = FormPhase::create(['title' => 'Fase Uji', 'is_active' => true]);
    $built = buildAccessibleFormPhaseDetail($user, $organization, $accessControl, $formPhase);

    return [
        'user' => $user,
        'form' => $form,
        'period' => $built['period'],
        'detail' => $built['detail'],
        // Jalur HTTP langkah 1 mengirim form_phase_id, bukan detail-nya.
        'phase' => $formPhase,
    ];
}

/**
 * Bangun satu FormAccessControl yang bisa diakses $user (permission +
 * organization subtree cocok), berikut satu FormPhaseDetail di bawah
 * $formPhase yang diberikan.
 *
 * @return array{form: Form, detail: FormPhaseDetail, period: SubmissionPeriod}
 */
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
