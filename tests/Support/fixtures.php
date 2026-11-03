<?php

// tests/Support/fixtures.php
//
// Fixture yang dipakai lintas berkas test. Sebelumnya tinggal di dalam
// berkas test tertentu, jadi hanya terbaca kalau berkas itu kebetulan ikut
// dimuat — test yang dijalankan sendirian langsung pecah. Di sini ia selalu
// tersedia, dan tempatnya jelas saat ada yang perlu menambah.

use App\Actions\Review\AssignEvaluationForm;
use App\Enums\DeadlineType;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldResponse;
use App\Models\ReviewFormResponse;
use App\Models\ReviewSummary;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Submission\EvaluationDueDate;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * BR-FE-25 — jenis dan siklus dari label tampilannya, supaya fixture skenario tetap menyebut
 * "Batas Evaluasi" atau "Batas Laporan Monev I" seperti teks BDD-nya.
 *
 * @return array{type: DeadlineType, cycle: int|null}
 */
function deadlineTypeOf(string $label): array
{
    foreach (DeadlineType::cases() as $type) {
        foreach ([null, ...range(1, 10)] as $cycle) {
            if ($type->label($cycle) === $label) {
                return ['type' => $type, 'cycle' => $cycle];
            }
        }
    }

    throw new InvalidArgumentException("Label tenggat tak dikenal: {$label}");
}

/**
 * Pasangan (jenis, siklus) ke-$i yang berbeda-beda, untuk test yang hanya butuh beberapa
 * tanggal dalam satu periode tanpa peduli jenisnya (BR-FE-25: unik per periode).
 *
 * @return array{type: DeadlineType, cycle: int|null}
 */
function deadlineSlot(int $i): array
{
    $cases = DeadlineType::cases();

    return ['type' => $cases[$i % count($cases)], 'cycle' => $i < count($cases) ? null : intdiv($i, count($cases))];
}

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
    $submissionDate = SubmissionDate::create([
        'type' => DeadlineType::Submit,
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

    // Sepasang izin yang dipegang peran `researcher` di PermissionSeeder.
    Permission::findOrCreate('submissions.create', 'web');
    Permission::findOrCreate('submissions.view-own', 'web');
    $user->givePermissionTo('submissions.create', 'submissions.view-own');

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
        ...deadlineTypeOf($label),
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
    $monev1 = monevDetail($phase, 3, 'Batas Laporan Monev I', $monev1NeedsReview);
    $monev2 = monevDetail($phase, 5, 'Batas Laporan Monev II');

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
        'type' => DeadlineType::Submit,
        'datetime' => $dueA,
        'submission_period_id' => $periodA->id,
    ]);
    // BR-FE-26 — detail yang direview memegang Batas Evaluasi dari periode yang sama.
    $evaluationA = $needsReview ? SubmissionDate::create([
        'type' => DeadlineType::Evaluation,
        'datetime' => $dueA,
        'submission_period_id' => $periodA->id,
    ]) : null;

    $periodB = SubmissionPeriod::create(['name' => "Periode B {$tag}"]);
    $dueB = now()->addDays(20)->startOfSecond();
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
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
        'evaluation_date_id' => $evaluationA?->id,
        'needs_review' => $needsReview,
    ]);

    return compact('form', 'detail', 'periodA', 'dueA', 'periodB', 'dueB');
}

/**
 * Detail pengajuan ber-review seperti jalur produksi (AssertReviewedPhaseDetail): ia memegang
 * "Batas Submit" peneliti dan "Batas Evaluasi" reviewer sendiri (BR-FE-26), dan memiliki
 * ReviewEvaluationForm-nya. Periodenya punya tanggal lain yang lebih akhir, milik Monev I.
 *
 * @return array{form: Form, submitDetail: FormPhaseDetail, evaluationForm: ReviewEvaluationForm, period: SubmissionPeriod, dueSubmit: Carbon, dueEvaluation: Carbon}
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
        'type' => DeadlineType::Submit,
        'datetime' => $dueSubmit,
        'submission_period_id' => $period->id,
    ]);

    $dueEvaluation = now()->addDays(17)->startOfSecond();
    $evaluationDate = SubmissionDate::create([
        'type' => DeadlineType::Evaluation,
        'datetime' => $dueEvaluation,
        'submission_period_id' => $period->id,
    ]);

    // Tanggal terakhir period, milik tahap lain — bukan tenggat evaluasi.
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'cycle' => 1,
        'datetime' => now()->addDays(40)->startOfSecond(),
        'submission_period_id' => $period->id,
    ]);

    $submitDetail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => PhaseType::create(['name' => "Tipe Pengajuan {$tag}"])->id,
        'order' => 1,
        'submission_date_id' => $submitDate->id,
        'evaluation_date_id' => $evaluationDate->id,
        'needs_review' => true,
    ]);

    $evaluationForm = ReviewEvaluationForm::create([
        'title' => "Substansi {$tag}",
        'form_phase_detail_id' => $submitDetail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    return compact('form', 'submitDetail', 'evaluationForm', 'period', 'dueSubmit', 'dueEvaluation');
}

/**
 * Satu jawaban field evaluasi lengkap dengan rantai induknya, supaya test
 * formatter dan validator tidak masing-masing merakit tujuh model.
 */
function evaluationFieldResponse(string $fieldType, ?string $value, bool $isRequired = false): ReviewFormFieldResponse
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $reviewer->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => 'draft',
    ]);

    $field = ReviewFormField::create([
        'review_evaluation_form_id' => $form->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => $fieldType])->id,
        'label' => 'Uji',
        'is_required' => $isRequired,
        'order' => 1,
    ]);

    return ReviewFormFieldResponse::create([
        'review_form_response_id' => $response->id,
        'review_form_field_id' => $field->id,
        'value' => $value,
    ]);
}

/**
 * Pengajuan dengan satu ulir terbuka dari reviewer internal "Penilai Ulir".
 *
 * @return array{0: User, 1: User, 2: FormSubmission}
 */
function threadAuthorFixture(string $status = UnderReview::class): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $status,
        'is_submitted' => true,
    ]);

    $author = User::factory()->create(['name' => 'Penilai Ulir']);
    $reviewer = Reviewer::factory()->create([
        'user_id' => $author->id,
        'reviewer_type' => 'internal',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perbaiki metodologi.',
    ]);

    return [$owner, $author, $submission];
}

/**
 * Detail "Pengajuan" ber-review seperti jalur produksi: tiga tenggatnya sendiri (BR-FE-26), form
 * evaluasi yang melekat padanya, satu pengajuan UNDER_REVIEW, dan reviewer aktif yang ditugaskan
 * lewat AssignEvaluationForm + EvaluationDueDate (jalur AssignReviewers).
 *
 * @return array{period: SubmissionPeriod, detail: FormPhaseDetail, submission: FormSubmission, reviewer: User, assignment: ReviewerFormAssignment}
 */
function reviewedDetailFixture(string $submit, string $evaluation, ?string $revision = null): array
{
    $period = SubmissionPeriod::factory()->create(['is_force_closed' => false]);
    $date = fn (DeadlineType $type, ?string $at): ?int => $at === null ? null : SubmissionDate::create([
        'type' => $type, 'datetime' => $at, 'submission_period_id' => $period->id,
    ])->id;
    $detail = FormPhaseDetail::factory()->create([
        'needs_review' => true,
        'submission_date_id' => $date(DeadlineType::Submit, $submit),
        'evaluation_date_id' => $date(DeadlineType::Evaluation, $evaluation),
        'revision_date_id' => $date(DeadlineType::Revision, $revision),
    ]);
    $form = ReviewEvaluationForm::create([
        'title' => 'Form Substansi', 'form_phase_detail_id' => $detail->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);
    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'submission_period_id' => $period->id,
        'submitted_by' => User::factory()->create()->id,
        'status' => UnderReview::class,
        'is_submitted' => true,
    ]);
    $reviewer = User::factory()->create()->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));
    $seat = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $reviewer->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
    AssignEvaluationForm::run($seat, $form->id, true, EvaluationDueDate::run($form));

    return [
        'period' => $period,
        'detail' => $detail,
        'submission' => $submission,
        'reviewer' => $reviewer,
        'assignment' => ReviewerFormAssignment::query()->where('submission_reviewer_id', $seat->id)->sole(),
    ];
}

/**
 * W80 — tipe Form yang boleh dipilih Form baru. Basis data segar memberi id pertama
 * tipe mana pun yang dibuat lebih dulu, dan id itu adalah BIODATA_ID.
 */
function regularFormType(): FormType
{
    $type = FormType::factory()->create();

    return $type->id === FormType::BIODATA_ID ? FormType::factory()->create() : $type;
}

/** Tipe Biodata pada id-nya yang tetap; dipanggil sesudah regularFormType() agar urutan id tak bentrok. */
function biodataFormType(): FormType
{
    return FormType::query()->find(FormType::BIODATA_ID)
        ?? FormType::forceCreate(['id' => FormType::BIODATA_ID, 'name' => 'Biodata']);
}
