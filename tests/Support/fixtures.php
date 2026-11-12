<?php

// tests/Support/fixtures.php
//
// Fixture yang dipakai lintas berkas test. Sebelumnya tinggal di dalam
// berkas test tertentu, jadi hanya terbaca kalau berkas itu kebetulan ikut
// dimuat — test yang dijalankan sendirian langsung pecah. Di sini ia selalu
// tersedia, dan tempatnya jelas saat ada yang perlu menambah.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\AssignReviewer;
use App\Actions\Review\FinishReviewRound;
use App\Actions\Review\JoinReviewRound;
use App\Data\Request\ResearchOutputInput;
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
use App\Models\ReviewEvaluationFormVersion;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldResponse;
use App\Models\ReviewFormResponse;
use App\Models\ReviewRound;
use App\Models\ReviewSummary;
use App\Models\StepEvaluationForm;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Submission\EvaluationDueDate;
use App\States\Submission\Approved;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => now()->addWeek(),
        'submission_period_id' => $period->id,
    ]);

    $detail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'deadline_type' => DeadlineType::Submit,
    ]);
    // R1-W2 — pengajuan baru mensyaratkan fase tertaut periode (submission_period_phases).
    SubmissionPeriodPhase::query()->firstOrCreate(['submission_period_id' => $period->id, 'form_phase_id' => $formPhase->id]);

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
 * User terverifikasi yang memegang peran DDD sungguhan dari `PermissionSeeder`, bukan izin
 * tempelan: lubang W138 lolos dari setiap test yang memberi izin satu per satu.
 */
function seededRole(string $role): User
{
    test()->seed(PermissionSeeder::class);

    $user = verifiedResearcher();
    $user->assignRole($role);

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

/** Field teks pada $form; wizard v2 menyimpannya di langkah 2 (isian). */
function wizardTextField(Form $form, string $label = 'Judul'): FormField
{
    return FormField::create([
        'form_id' => $form->id,
        'label' => $label,
        'is_required' => false,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'order' => 1,
    ]);
}

/**
 * Isi POST `user.submissions.wizard.save`. Form, periode, dan tahap diisi WizardStepRequest dari
 * pengajuan di route, jadi yang dikirim hanya jawaban, cap optimistic lock (BR-FE-14), dan tujuan.
 *
 * @param  list<array{form_field_id: int, value: mixed}>  $responses
 * @return array{last_updated_at: ?string, goto: int, responses: list<array{form_field_id: int, value: mixed}>}
 */
function wizardPayload(?string $lastUpdatedAt, int $goto, array $responses = []): array
{
    return ['last_updated_at' => $lastUpdatedAt, 'goto' => $goto, 'responses' => $responses];
}

/**
 * `wizardFixture` + pengajuan APPROVED di detail pengajuan + detail "Upload Luaran" (DDD monev
 * Detail 7) di tahap yang sama, bertenggat Batas Akhir pada `$deadline` (W139).
 *
 * @return array{user: User, form: Form, period: SubmissionPeriod, detail: FormPhaseDetail, phase: FormPhase, outputDetail: FormPhaseDetail, submission: FormSubmission}
 */
function outputUploadFixture(string $deadline = '2026-12-01 23:59:00'): array
{
    $fx = wizardFixture();

    calendarDate($fx['period'], DeadlineType::Output, $deadline);
    $outputDetail = FormPhaseDetail::create([
        'form_phase_id' => $fx['phase']->id,
        'form_access_control_id' => $fx['detail']->form_access_control_id,
        'phase_type_id' => $fx['detail']->phase_type_id,
        'order' => $fx['detail']->order + 6,
        'needs_review' => false,
        'deadline_type' => DeadlineType::Output,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    return [...$fx, 'outputDetail' => $outputDetail, 'submission' => $submission];
}

/**
 * Masukan luaran (W141): berkas berupa unggahan palsu `bukti-{n}.pdf`, bukan path karangan klien.
 *
 * @param  array<string, mixed>  $metadata
 */
function outputInput(string $type, array $metadata = [], int $files = 0): ResearchOutputInput
{
    $uploads = [];

    for ($n = 1; $n <= $files; $n++) {
        $uploads[] = UploadedFile::fake()->create("bukti-{$n}.pdf", 120, 'application/pdf');
    }

    return new ResearchOutputInput($type, $metadata, $uploads);
}

/**
 * BR-FE-26 (ADR 0019) — semua langkah satu fase membaca satu kalender periode: tanggalnya masuk
 * periode pertama yang menautkan fase itu (periode baru bila belum tertaut).
 */
function monevDetail(FormPhase $phase, int $order, string $label, bool $needsReview = false, string $when = '+1 month'): FormPhaseDetail
{
    $period = SubmissionPeriodPhase::query()->where('form_phase_id', $phase->id)->orderBy('id')
        ->first()?->submissionPeriod ?? SubmissionPeriod::factory()->create();
    $kind = deadlineTypeOf($label);
    SubmissionDate::query()->firstOrCreate(
        [...$kind, 'submission_period_id' => $period->id],
        ['datetime' => now()->modify($when)],
    );

    return FormPhaseDetail::factory()->forPeriod($period)->create([
        'form_phase_id' => $phase->id,
        'deadline_type' => $kind['type'],
        'deadline_cycle' => $kind['cycle'],
        'order' => $order,
        'needs_review' => $needsReview,
    ]);
}

/**
 * BR-FE-07 (ADR 0019) — langkah tanpa rujukan tanggal: hanya jenis + siklus. Tanggalnya dibaca
 * dari kalender periode pengajuan (`calendarDate`).
 */
function scheduleStep(FormPhase $phase, ?int $cycle = null, bool $needsReview = true, int $order = 1, DeadlineType $type = DeadlineType::Submit): FormPhaseDetail
{
    $tag = fake()->unique()->numerify('####-####');
    $access = FormAccessControl::create([
        'form_id' => Form::factory()->create(['form_type_id' => regularFormType()->id])->id,
        'permission' => 'submissions.create',
        'organization_id' => Organization::create(['name' => "Organisasi Jadwal {$tag}", 'type' => 'faculty'])->id,
    ]);

    return FormPhaseDetail::create([
        'form_phase_id' => $phase->id,
        'form_access_control_id' => $access->id,
        'phase_type_id' => PhaseType::create(['name' => "Tipe Jadwal {$tag}"])->id,
        'order' => $order,
        'needs_review' => $needsReview,
        'deadline_type' => $type,
        'deadline_cycle' => $cycle,
    ]);
}

/** Periode yang menautkan `$phase` (submission_period_phases), tanpa tanggal. */
function schedulePeriod(FormPhase $phase, string $name): SubmissionPeriod
{
    $period = SubmissionPeriod::create(['name' => $name]);
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $phase->id]);

    return $period;
}

/** Satu tanggal di kalender periode (BR-FE-25: unik per jenis + siklus). */
function calendarDate(SubmissionPeriod $period, DeadlineType $type, Carbon|string $at, ?int $cycle = null): SubmissionDate
{
    return SubmissionDate::create([
        'submission_period_id' => $period->id,
        'type' => $type,
        'cycle' => $cycle,
        'datetime' => $at,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function scheduledSubmission(FormPhaseDetail $step, SubmissionPeriod $period, array $attributes = []): FormSubmission
{
    return FormSubmission::factory()->create($attributes + [
        'form_id' => $step->formAccessControl?->form_id,
        'form_phase_detail_id' => $step->id,
        'submission_period_id' => $period->id,
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
        // Bukan Biodata: daftar monev mengecualikannya (W128).
        'form_id' => Form::factory()->create(['form_type_id' => regularFormType()->id])->id,
        'submitted_by' => $owner->id,
        'form_phase_detail_id' => $pengajuan->id,
        'status' => $parentStatus,
        'is_submitted' => true,
    ]);

    return compact('parent', 'owner', 'pengajuan', 'monev1', 'monev2');
}

/**
 * `monevFixture` + Form laporan Monev I: satu field teks wajib dan satu field berkas opsional.
 * Field dibuat sebelum laporan, jadi wajibnya berlaku (BR-FE-11).
 *
 * @return array{parent: FormSubmission, owner: User, pengajuan: FormPhaseDetail, monev1: FormPhaseDetail, monev2: FormPhaseDetail, summary: FormField, logbook: FormField}
 */
function monevFormFixture(): array
{
    $fx = monevFixture();
    $form = $fx['monev1']->formAccessControl->form;

    $summary = FormField::create([
        'form_id' => $form->id,
        'label' => 'Ringkasan Kemajuan',
        'is_required' => true,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'textarea'])->id,
        'order' => 1,
    ]);
    $logbook = FormField::create([
        'form_id' => $form->id,
        'label' => 'Logbook Penelitian',
        'is_required' => false,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'file'])->id,
        'order' => 2,
    ]);

    return [...$fx, 'summary' => $summary, 'logbook' => $logbook];
}

/**
 * Laporan monev yang sudah punya satu evaluasi reviewer berstatus tertentu.
 *
 * @return array{laporan: FormSubmission, response: ReviewFormResponse, reviewer: Reviewer}
 */
function monevReportWithEvaluation(array $fx, string $responseStatus): array
{
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    $reviewer = Reviewer::factory()->create([
        'user_id' => verifiedResearcher()->id,
        'reviewer_type' => 'internal',
        'start_date' => now()->subYear(),
        'end_date' => now()->addYear(),
    ]);

    $penugasan = AssignReviewer::run($laporan, $reviewer);

    $form = evaluationFormOn($fx['monev1'], [], title: 'Lembar Penilaian Monev');

    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $penugasan->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
    ]);

    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id, 'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'status' => $responseStatus,
        'submitted_at' => $responseStatus === 'submitted' ? now() : null,
    ]);

    return ['laporan' => $laporan, 'response' => $response, 'reviewer' => $reviewer];
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
    calendarDate($periodA, DeadlineType::Submit, $dueA);
    // BR-FE-26 — langkah yang direview membaca Batas Evaluasi dari kalender periode yang sama.
    if ($needsReview) {
        calendarDate($periodA, DeadlineType::Evaluation, $dueA);
    }

    $periodB = SubmissionPeriod::create(['name' => "Periode B {$tag}"]);
    $dueB = now()->addDays(20)->startOfSecond();
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => $dueB,
        'submission_period_id' => $periodB->id,
    ]);

    // ADR 0019 — kedua periode menautkan fasenya; A lebih dulu, jadi pabrik pengajuan memilih A.
    foreach ([$periodA, $periodB] as $period) {
        SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $formPhase->id]);
    }

    $detail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => $phaseType->id,
        'order' => 1,
        'deadline_type' => DeadlineType::Submit,
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
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => $dueSubmit,
        'submission_period_id' => $period->id,
    ]);

    $dueEvaluation = now()->addDays(17)->startOfSecond();
    SubmissionDate::create([
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

    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $formPhase->id]);
    $submitDetail = FormPhaseDetail::create([
        'form_phase_id' => $formPhase->id,
        'form_access_control_id' => $accessControl->id,
        'phase_type_id' => PhaseType::create(['name' => "Tipe Pengajuan {$tag}"])->id,
        'order' => 1,
        'deadline_type' => DeadlineType::Submit,
        'needs_review' => true,
    ]);

    $evaluationForm = evaluationFormOn($submitDetail, [], title: "Substansi {$tag}");

    return compact('form', 'submitDetail', 'evaluationForm', 'period', 'dueSubmit', 'dueEvaluation');
}

/**
 * Satu jawaban field evaluasi lengkap dengan rantai induknya, supaya test
 * formatter dan validator tidak masing-masing merakit tujuh model.
 */
/** @param  array<string, scalar|null>|null  $validationRules */
function evaluationFieldResponse(string $fieldType, ?string $value, bool $isRequired = false, ?array $validationRules = null): ReviewFormFieldResponse
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $form = evaluationFormOn($detail, [], title: 'Substansi');

    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $reviewer->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id, 'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'status' => 'draft',
    ]);

    $field = ReviewFormField::create([
        'review_evaluation_form_version_id' => $form->latestVersion->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => $fieldType])->id,
        'label' => 'Uji',
        'is_required' => $isRequired,
        'order' => 1,
        'validation_rules' => $validationRules,
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
    // ADR 0019 — tiga tanggal kerja langkah ini ada di kalender periodenya (BR-FE-26).
    foreach ([[DeadlineType::Submit, $submit], [DeadlineType::Evaluation, $evaluation], [DeadlineType::Revision, $revision]] as [$type, $at]) {
        if ($at !== null) {
            calendarDate($period, $type, $at);
        }
    }
    $detail = FormPhaseDetail::factory()->forPeriod($period)->create(['needs_review' => true]);
    $form = evaluationFormOn($detail, [], title: 'Form Substansi');
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
    AssignEvaluationForm::run($seat, $form->id, true, EvaluationDueDate::run($submission));
    // BR-REV-16 — seperti AssignReviewer: kursi baru ikut putaran penilaian.
    JoinReviewRound::run($seat);

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

/**
 * R1 — pengajuan induk terkirim dengan entri submit pertama pada $submittedAt (W149),
 * diajukan dari $unit bila ada (fakultas/prodi menurut unit pengaju).
 *
 * @param  array<string, mixed>  $attributes
 */
function reportedSubmission(array $attributes = [], string $submittedAt = '2026-03-02 08:00:00', ?Organization $unit = null): FormSubmission
{
    if ($unit !== null) {
        $attributes['submitted_by'] = UserProfile::factory()->create(['organization_id' => $unit->id])->user_id;
    }
    $submission = FormSubmission::factory()->create(['is_submitted' => true, 'status' => Submitted::class, ...$attributes]);
    test()->travelTo($submittedAt);
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $submission, $submission->submittedBy);
    test()->travelBack();

    return $submission;
}

/** BR-IAM-21, W47 — pemegang izin baca user yang boleh melihat aktivitas sesi. */
function sessionViewer(string $permission): User
{
    $user = User::factory()->create(['is_active' => true, 'name' => 'Aaa Penonton']);
    Permission::findOrCreate($permission, 'web');
    $user->givePermissionTo($permission);

    return $user;
}

/**
 * Pengajuan utama pada fase yang (tidak) butuh review — fixture skenario `outcome.feature`.
 */
function outcomeSubmission(string $state = UnderReview::class, bool $needsReview = true): FormSubmission
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => $needsReview]);

    return FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'status' => $state,
        'form_phase_detail_id' => $detail->id,
        // Pengajuan yang sudah masuk review sudah tersubmit; halaman kelola Operator menuntutnya.
        'is_submitted' => true,
    ]);
}

/**
 * Satu kursi reviewer aktif. BR-REV-16 — ikut putaran review lewat jalur produksi
 * (`JoinReviewRound`, dipanggil `AssignReviewer`), jadi fixture tidak menyimpang darinya.
 */
function reviewerOn(FormSubmission $submission, string $evaluationStatus): SubmissionReviewer
{
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    $seat = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluationStatus,
        'status' => 'active',
    ]);

    JoinReviewRound::run($seat);

    return $seat;
}

function openSummaryOn(FormSubmission $submission, SubmissionReviewer $assignment, string $status = 'open'): ReviewSummary
{
    return ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $assignment->reviewer_id,
        'status' => $status,
        'summary_notes' => 'Perkuat metodologi.',
    ]);
}

/** BR-REV-16 — pemegang kursi menekan Selesai menilai/memeriksa. */
function finishSeat(SubmissionReviewer $seat): ReviewRound
{
    return FinishReviewRound::run($seat->refresh(), $seat->reviewer->user);
}

/**
 * BR-REV-05 — pengajuan siap diputuskan: satu kursi `completed` yang sudah Selesai menilai, sehingga
 * putaran penilaiannya selesai `ready`, lewat jalur produksi (FinishReviewRound).
 */
function readyForDecision(): FormSubmission
{
    $submission = outcomeSubmission();
    finishSeat(reviewerOn($submission, 'completed'));

    return $submission->refresh();
}

/**
 * ADR 0019 — tanggal yang dibaca langkah di kalender periode (default: periode pertama yang
 * menautkan fasenya), lewat satu pintu `SubmissionPeriod::deadlineFor`.
 */
function stepDate(FormPhaseDetail $step, ?SubmissionPeriod $period = null, ?DeadlineType $type = null): SubmissionDate
{
    $period ??= SubmissionPeriodPhase::query()->where('form_phase_id', $step->form_phase_id)->orderBy('id')
        ->firstOrFail()->submissionPeriod;

    return $period?->deadlineFor($step, $type ?? $step->deadline_type) ?? throw new RuntimeException('Langkah tidak punya tanggal di kalender periode ini.');
}

/**
 * BR-REV-18 — Form Evaluasi pustaka dengan Versi 1 terbit dan terpasang di `$detail`, tanpa audit.
 * Field: `type` nama FieldType, `required` bawaan false.
 *
 * @param  list<array{type: string, label: string, required?: bool}>  $fields
 */
function evaluationFormOn(FormPhaseDetail $detail, array $fields = [['type' => 'Text', 'label' => 'Catatan']], bool $required = true, bool $active = true, ?string $title = null): ReviewEvaluationForm
{
    $form = ReviewEvaluationForm::create(['title' => $title ?? 'Form Evaluasi '.fake()->unique()->numerify('####')]);
    publishVersion($form, $fields);
    StepEvaluationForm::create([
        'form_phase_detail_id' => $detail->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => $required,
        'is_active' => $active,
        'order' => StepEvaluationForm::query()->where('form_phase_detail_id', $detail->id)->max('order') + 1,
    ]);

    return $form;
}

/**
 * Versi berikutnya `$form` berisi `$fields`, tanpa audit (jalur produksinya PublishEvaluationFormVersion).
 *
 * @param  list<array{type: string, label: string, required?: bool}>  $fields
 */
function publishVersion(ReviewEvaluationForm $form, array $fields): ReviewEvaluationFormVersion
{
    $version = $form->versions()->create([
        'number' => (int) $form->versions()->max('number') + 1,
        'published_at' => now(),
    ]);
    foreach ($fields as $index => $field) {
        $version->reviewFormFields()->create([
            'field_type_id' => FieldType::firstOrCreate(['name' => $field['type']])->id,
            'label' => $field['label'],
            'is_required' => $field['required'] ?? false,
            'order' => $index + 1,
        ]);
    }
    $form->unsetRelation('latestVersion');

    return $version;
}

/** Jawaban evaluasi pada versi terbaru form penugasannya, tanpa lewat OpenEvaluationResponse. */
function evaluationResponseFor(ReviewerFormAssignment $assignment, string $status = 'draft'): ReviewFormResponse
{
    return ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id, 'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion->id,
        'review_evaluation_form_version_id' => $assignment->reviewEvaluationForm->latestVersion?->id,
        'status' => $status,
        'submitted_at' => $status === 'submitted' ? now() : null,
    ]);
}
