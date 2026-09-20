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
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
 * User yang benar-benar bisa mengirim pengajuan: punya UserProfile, sudah
 * terverifikasi, dan profilnya lengkap.
 *
 * `User::factory()->create()` telanjang bukan keadaan yang bisa dihasilkan
 * sistem — setiap akun lahir bersama profilnya, entah lewat jalur mandiri atau
 * undangan (BR-IAM-12). Fixture yang mengabaikan itu menguji orang yang tidak
 * pernah ada, dan lolos dari gerbang BR-IAM-04 tanpa membuktikan apa pun.
 */
function verifiedResearcher(): User
{
    $user = User::factory()->create();

    UserProfile::factory()->create(['user_id' => $user->id]);

    return $user->refresh();
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
function draftPayload(Form $form, SubmissionPeriod $period, FormPhase $formPhase, ?string $lastUpdatedAt = null): array
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
        // BR-FE-14 — cap waktu optimistic lock. Dibiarkan null kalau pemanggil
        // memang sedang menguji jalur tanpa baris pengajuan.
        'last_updated_at' => $lastUpdatedAt,
        'responses' => [
            ['form_field_id' => $field->id, 'value' => 'Contoh nilai'],
        ],
    ];
}

function monevDetail(FormPhase $phase, int $order, string $label, bool $needsReview = false, string $when = '+1 month'): FormPhaseDetail
{
    $date = SubmissionDate::create([
        'submission_date_label_id' => SubmissionDateLabel::firstOrCreate(['name' => $label])->id,
        'submission_period_id' => SubmissionPeriod::factory()->create()->id,
        'datetime' => now()->modify($when),
    ]);

    return FormPhaseDetail::factory()->create([
        'form_phase_id' => $phase->id,
        'submission_date_id' => $date->id,
        'order' => $order,
        'needs_review' => $needsReview,
    ]);
}

/**
 * Induk APPROVED beserta tiga detail berurutan: Pengajuan, Monev I, Monev II.
 *
 * @return array{parent: FormSubmission, owner: User, pengajuan: FormPhaseDetail, monev1: FormPhaseDetail, monev2: FormPhaseDetail}
 */
function monevFixture(bool $monev1NeedsReview = false, string $parentStatus = Approved::class): array
{
    $phase = FormPhase::create(['title' => 'Siklus '.fake()->unique()->numerify('####'), 'is_active' => true]);

    $pengajuan = monevDetail($phase, 1, 'Batas Submit');
    $monev1 = monevDetail($phase, 3, 'Batas Monev I', $monev1NeedsReview);
    $monev2 = monevDetail($phase, 5, 'Batas Monev II');

    $owner = verifiedResearcher();

    $parent = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'form_phase_detail_id' => $pengajuan->id,
        'status' => $parentStatus,
        'is_submitted' => true,
    ]);

    return compact('parent', 'owner', 'pengajuan', 'monev1', 'monev2');
}

/**
 * Dua SubmissionPeriod berbagi satu FormPhase lewat pivot
 * `submission_period_phases` — bentuk yang ADR 0004 dibuat untuk dijinakkan.
 * Period A dimiliki pengajuan lewat FK; period B hanya kebetulan sefase.
 *
 * @return array{form: Form, detail: FormPhaseDetail, periodA: SubmissionPeriod, dueA: Carbon, periodB: SubmissionPeriod, dueB: Carbon}
 */
function phaseSharedByTwoPeriods(string $tag, bool $needsReview = false): array
{
    $form = Form::factory()->create();

    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => Organization::create(['name' => "Organisasi {$tag}", 'type' => 'faculty'])->id,
    ]);

    $phaseType = PhaseType::create(['name' => "Tipe Fase {$tag}"]);
    $formPhase = FormPhase::create(['title' => "Fase {$tag}", 'is_active' => true]);

    $periodA = SubmissionPeriod::create(['name' => "Periode A {$tag}"]);
    $dueA = now()->addDays(5)->startOfSecond();
    $dateA = SubmissionDate::create([
        'submission_date_label_id' => SubmissionDateLabel::create(['name' => "Label A {$tag}"])->id,
        'datetime' => $dueA,
        'submission_period_id' => $periodA->id,
    ]);

    $periodB = SubmissionPeriod::create(['name' => "Periode B {$tag}"]);
    $dueB = now()->addDays(20)->startOfSecond();
    SubmissionDate::create([
        'submission_date_label_id' => SubmissionDateLabel::create(['name' => "Label B {$tag}"])->id,
        'datetime' => $dueB,
        'submission_period_id' => $periodB->id,
    ]);

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $periodB->id,
        'form_phase_id' => $formPhase->id,
    ]);

    $detail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'submission_date_id' => $dateA->id,
        'needs_review' => $needsReview,
    ]);

    return compact('form', 'detail', 'periodA', 'dueA', 'periodB', 'dueB');
}

/**
 * Satu FormPhase dengan dua detail seperti docs/…/supporting/02_monev.md:
 * detail pengajuan bertenggat "Batas Submit", dan detail evaluasi bertenggat
 * "Batas Evaluasi" sendiri yang memiliki ReviewEvaluationForm-nya (BR-FE-17).
 *
 * @return array{form: Form, submitDetail: FormPhaseDetail, evaluationDetail: FormPhaseDetail, evaluationForm: ReviewEvaluationForm, period: SubmissionPeriod, dueSubmit: Carbon, dueEvaluation: Carbon}
 */
function phaseWithOwnEvaluationDeadline(string $tag): array
{
    $form = Form::factory()->create();

    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => Organization::create(['name' => "Organisasi {$tag}", 'type' => 'faculty'])->id,
    ]);

    $formPhase = FormPhase::create(['title' => "Fase {$tag}", 'is_active' => true]);
    $period = SubmissionPeriod::create(['name' => "Periode {$tag}"]);

    $dueSubmit = now()->addDays(3)->startOfSecond();
    $submitDate = SubmissionDate::create([
        'submission_date_label_id' => SubmissionDateLabel::create(['name' => "Batas Submit {$tag}"])->id,
        'datetime' => $dueSubmit,
        'submission_period_id' => $period->id,
    ]);

    $dueEvaluation = now()->addDays(17)->startOfSecond();
    $evaluationDate = SubmissionDate::create([
        'submission_date_label_id' => SubmissionDateLabel::create(['name' => "Batas Evaluasi {$tag}"])->id,
        'datetime' => $dueEvaluation,
        'submission_period_id' => $period->id,
    ]);

    // Tanggal terakhir period, milik tahap lain — bukan tenggat evaluasi.
    SubmissionDate::create([
        'submission_date_label_id' => SubmissionDateLabel::create(['name' => "Batas Akhir {$tag}"])->id,
        'datetime' => now()->addDays(40)->startOfSecond(),
        'submission_period_id' => $period->id,
    ]);

    $submitDetail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => PhaseType::create(['name' => "Tipe Pengajuan {$tag}"])->id,
        'order' => 1,
        'submission_date_id' => $submitDate->id,
        'needs_review' => true,
    ]);

    $evaluationDetail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => PhaseType::create(['name' => "Tipe Evaluasi {$tag}"])->id,
        'order' => 2,
        'submission_date_id' => $evaluationDate->id,
    ]);

    $evaluationForm = ReviewEvaluationForm::create([
        'title' => "Substansi {$tag}",
        'form_phase_detail_id' => $evaluationDetail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    return compact('form', 'submitDetail', 'evaluationDetail', 'evaluationForm', 'period', 'dueSubmit', 'dueEvaluation');
}
