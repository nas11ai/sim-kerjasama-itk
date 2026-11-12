<?php

// tests/Feature/EndToEnd/SubmissionReviewFlowTest.php
//
// Satu alur utuh dari mulai mengajukan sampai disetujui, seluruhnya lewat
// HTTP — bukan memanggil Action langsung.
//
//   peneliti mulai → isi → submit  (M1)
//   operator tugaskan reviewer     (M2, BR-REV-01)
//   evaluasi masuk, ulir dibuka lalu ditutup (M2)
//   reviewer Selesai menilai       (M2, BR-REV-16)
//   Operator Setujui → APPROVED    (M2, BR-REV-05, ADR 0018)
//
// Gunanya bukan menggantikan test per-skenario, melainkan membuktikan
// bahwa potongan-potongan itu benar-benar tersambung. Test unit yang semuanya
// hijau tetap bisa menyisakan aplikasi yang tidak jalan — persis keadaan
// sebelum Action M2 disambungkan ke controller.

use App\Enums\DeadlineType;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

function operatorUser(): User
{
    Permission::findOrCreate('users.manage', 'web');
    Permission::findOrCreate('reviewers.assign', 'web');

    Permission::findOrCreate('submissions.view-all', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo(['users.manage', 'reviewers.assign', 'submissions.view-all']);

    return $operator;
}

function activeReviewerRecord(): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

it('menyusuri alur pengajuan sampai disetujui lewat HTTP', function () {
    $fx = wizardFixture();
    $researcher = $fx['user'];

    // Skema menetapkan ambang reviewer — bukan konstanta di kode (BR-REV-01).
    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => 2, 'max_reviewer_workload' => 10],
    ]);

    $fx['detail'] = restep($fx['detail'], ['needs_review' => true]);
    // Fase punya lembar penilaian, jadi kursi reviewer menunggu evaluasi (bukan `not_required`).
    evaluationFormOn($fx['detail'], [], title: 'Substansi');
    // BR-FE-36 — langkah Pengajuan yang direview butuh Batas Evaluasi supaya angkatannya siap.
    calendarDate($fx['period'], DeadlineType::Evaluation, now()->addWeeks(3));

    $textType = FieldType::firstOrCreate(['name' => 'text']);
    $judul = FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => $textType->id,
        'label' => 'Judul Penelitian',
        'is_required' => true,
        'order' => 1,
    ]);

    // ---------- 1. Peneliti memulai pengajuan (ADR 0012 langkah 1) ----------
    $this->actingAs($researcher)
        ->post(route('user.submissions.store'), [
            'form_id' => $fx['form']->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_id' => $fx['detail']->form_phase_id,
        ])
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $fx['form']->id)->sole();
    expect($submission->status)->toBeInstanceOf(Draft::class);

    $submission->update(['scheme_id' => $scheme->id]);

    // ---------- 2. Mengisi lalu submit (BR-SM-02, BR-FE-11) ----------
    // BR-FE-14 — simpan wizard membawa cap waktu yang dilihat penyunting.
    $this->actingAs($researcher)
        ->post(route('user.submissions.wizard.save', [$submission, 2]), wizardPayload(
            $submission->fresh()->updated_at->toIso8601String(),
            7,
            [['form_field_id' => $judul->id, 'value' => 'Analisis Potensi Biochar']],
        ))
        ->assertSessionHasNoErrors();

    $this->actingAs($researcher)
        ->post(route('user.submissions.submit', $submission))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class);

    // ---------- 3. Operator menugaskan reviewer (BR-REV-01) ----------
    $operator = operatorUser();
    $reviewerA = activeReviewerRecord();
    $reviewerB = activeReviewerRecord();

    $this->actingAs($operator)
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [$reviewerA->id, $reviewerB->id],
        ])
        ->assertRedirect();

    // Ambang tercapai — pengajuan masuk antrean review tanpa aksi tambahan.
    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);

    // ---------- 4. Evaluasi masuk ----------
    SubmissionReviewer::where('form_submission_id', $submission->id)
        ->update(['evaluation_status' => 'completed']);

    $userA = User::findOrFail($reviewerA->user_id)->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));
    $userB = User::findOrFail($reviewerB->user_id)->givePermissionTo('reviewers.evaluate');

    $this->actingAs($userA)
        ->post(route('reviewer.review-threads.store', $submission), ['summary_notes' => 'Perkuat metodologi sampling.'])
        ->assertSessionHasNoErrors();

    // ---------- 5. Ringkasan ditutup — tidak memutuskan apa pun (BR-REV-16) ----------
    // Hanya reviewer yang menutup ulir — Operator tidak (REV-DISCUSS-04).
    $this->actingAs($userA)
        ->post(route('review-summaries.resolve', ReviewSummary::where('form_submission_id', $submission->id)->sole()))
        ->assertRedirect();

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);

    // ---------- 6. Kedua reviewer Selesai menilai → siap diputuskan ----------
    $this->actingAs($userA)->post(route('reviewer.assignments.finish', $submission))->assertSessionHasNoErrors();
    $this->actingAs($userB)->post(route('reviewer.assignments.finish', $submission))->assertSessionHasNoErrors();

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);

    // ---------- 7. Operator menyetujui (BR-REV-05, ADR 0018) ----------
    $this->actingAs($operator)
        ->post(route('admin.submissions.approve', $submission))
        ->assertSessionHas('success', 'Pengajuan disetujui.');

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('menahan persetujuan selama satu evaluasi belum masuk', function () {
    $fx = wizardFixture();
    $fx['detail'] = restep($fx['detail'], ['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'scheme_id' => null,
        'status' => UnderReview::class,
    ]);

    $reviewerA = activeReviewerRecord();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewerA->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => activeReviewerRecord()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $summary = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewerA->id,
        'status' => 'open',
        'summary_notes' => 'Catatan.',
    ]);

    $this->actingAs(User::findOrFail($reviewerA->user_id))
        ->post(route('review-summaries.resolve', $summary))
        ->assertRedirect();

    // Ringkasan sudah ditutup, tapi satu evaluasi masih ditunggu — BR-REV-05
    // menuntut keduanya terpenuhi, bukan salah satunya.
    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});
