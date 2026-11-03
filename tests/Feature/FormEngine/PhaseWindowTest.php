<?php

// tests/Feature/FormEngine/PhaseWindowTest.php
//
// BR-FE-17 + BR-FE-26 — deadline sebuah FormPhaseDetail mengikat **aktor detail itu
// sendiri**, bukan seluruh alur. Detail yang direview memegang tiga tenggat: Batas Submit
// (pengisi), Batas Evaluasi (reviewer form evaluasi yang melekat padanya), dan Batas Revisi
// (pengisi saat NEEDS_REVISION).
//
// Fixture lama memasang form evaluasi ke detail evaluasi terpisah — bentuk yang ditolak jalur
// produksi (AssertReviewedPhaseDetail). Akibatnya test lolos sementara produksi memakai Batas
// Submit peneliti sebagai tenggat reviewer (E1b, temuan 2). Di sini form evaluasi melekat pada
// detail ber-`needs_review`, sama seperti produksi.

use App\Actions\FormSubmission\StartSubmission;
use App\Actions\Review\AssignEvaluationForm;
use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Submission\EvaluationDueDate;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\UnderReview;
use App\Support\PhaseAccessGate;
use Spatie\Permission\Models\Permission;

function windowDate(SubmissionPeriod $period, string $label, string $when): SubmissionDate
{
    return SubmissionDate::create([
        ...deadlineTypeOf($label),
        'submission_period_id' => $period->id,
        'datetime' => $when,
    ]);
}

/**
 * Detail pengajuan ber-review dengan Batas Submit dan Batas Evaluasi periodenya, form evaluasi
 * yang melekat padanya, dan reviewer aktif yang ditugaskan lewat jalur produksi.
 *
 * @return array{detail: FormPhaseDetail, period: SubmissionPeriod, reviewer: User, assignment: ReviewerFormAssignment}
 */
function reviewedWindow(string $submitWhen, string $evaluationWhen): array
{
    $period = SubmissionPeriod::factory()->create(['is_force_closed' => false]);
    $detail = FormPhaseDetail::factory()->create([
        'needs_review' => true,
        'submission_date_id' => windowDate($period, 'Batas Submit', $submitWhen)->id,
        'evaluation_date_id' => windowDate($period, 'Batas Evaluasi', $evaluationWhen)->id,
    ]);
    $form = ReviewEvaluationForm::create([
        'title' => 'Form Substansi', 'form_phase_detail_id' => $detail->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);
    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'submission_period_id' => $period->id,
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

    // Jalur AssignReviewers: tenggat penugasan dihitung EvaluationDueDate per form.
    AssignEvaluationForm::run($seat, $form->id, true, EvaluationDueDate::run($form));

    return [
        'detail' => $detail,
        'period' => $period,
        'reviewer' => $reviewer,
        'assignment' => ReviewerFormAssignment::query()->where('submission_reviewer_id', $seat->id)->sole(),
    ];
}

it('[FE-WINDOW-01] evaluasi tetap terbuka setelah batas submit lewat', function () {
    $fx = reviewedWindow(now()->subDay()->toDateTimeString(), now()->addWeek()->toDateTimeString());

    // Tenggat peneliti sudah lewat, tapi reviewer punya jendelanya sendiri.
    expect($fx['detail']->isWithinDeadline())->toBeFalse()
        ->and($fx['detail']->isWithinDeadline(DeadlineType::Evaluation))->toBeTrue()
        ->and($fx['assignment']->due_date->equalTo($fx['detail']->evaluationDate->datetime))->toBeTrue()
        ->and($fx['reviewer']->can('evaluate', $fx['assignment']))->toBeTrue();
});

it('[FE-WINDOW-02] evaluasi ditolak setelah batas evaluasinya sendiri lewat', function () {
    $fx = reviewedWindow(now()->subWeek()->toDateTimeString(), now()->subDay()->toDateTimeString());

    expect($fx['detail']->isWithinDeadline(DeadlineType::Evaluation))->toBeFalse()
        ->and($fx['reviewer']->can('evaluate', $fx['assignment']))->toBeFalse();
});

it('[FE-WINDOW-03] revisi punya jendelanya sendiri', function () {
    $period = SubmissionPeriod::factory()->create();
    $detail = FormPhaseDetail::factory()->create([
        'submission_date_id' => windowDate($period, 'Batas Submit', now()->subDay()->toDateTimeString())->id,
        'revision_date_id' => windowDate($period, 'Batas Revisi', now()->addWeek()->toDateTimeString())->id,
    ]);
    $revisi = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => NeedsRevision::class]);
    $draft = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => Draft::class]);

    // Tanpa jendela terpisah, janji BR-BUD-03 — anggaran tetap bisa disunting saat
    // NEEDS_REVISION — bergantung pada Batas Submit (E1b, temuan 3).
    expect(PhaseAccessGate::isOpenFor($revisi, $detail))->toBeTrue()
        ->and(PhaseAccessGate::isOpenFor($draft, $detail))->toBeFalse()
        ->and(PhaseAccessGate::deadlineFor($revisi, $detail)?->type)->toBe(DeadlineType::Revision);
});

it('[FE-WINDOW-03] tanpa Batas Revisi, revisi kembali memakai Batas Submit', function () {
    $period = SubmissionPeriod::factory()->create();
    $detail = FormPhaseDetail::factory()->create([
        'submission_date_id' => windowDate($period, 'Batas Submit', now()->addWeek()->toDateTimeString())->id,
    ]);
    $revisi = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => NeedsRevision::class]);

    expect(PhaseAccessGate::deadlineFor($revisi, $detail)?->id)->toBe($detail->submission_date_id)
        ->and(PhaseAccessGate::isOpenFor($revisi, $detail))->toBeTrue();
});

it('[FE-WINDOW-04] dua detail beraktor sama boleh berbagi satu tanggal', function () {
    $phase = FormPhase::create(['title' => 'Fase Uji Jendela', 'is_active' => true]);
    $date = windowDate(SubmissionPeriod::factory()->create(), 'Batas Submit', now()->addWeek()->toDateTimeString());

    // Yang dilarang berbagi tanggal adalah detail dengan aktor **berbeda**.
    // Dua tahap researcher berurutan wajar memakai batas yang sama.
    $satu = FormPhaseDetail::factory()->create([
        'form_phase_id' => $phase->id, 'submission_date_id' => $date->id,
        'order' => 1, 'needs_review' => false,
    ]);
    $dua = FormPhaseDetail::factory()->create([
        'form_phase_id' => $phase->id, 'submission_date_id' => $date->id,
        'order' => 2, 'needs_review' => false,
    ]);

    expect($satu->submission_date_id)->toBe($dua->submission_date_id)
        ->and($satu->isWithinDeadline())->toBeTrue()
        ->and($dua->isWithinDeadline())->toBeTrue();
});

it('[FE-WINDOW-02] periode yang ditutup paksa menutup semua jendela', function () {
    $fx = reviewedWindow(now()->addDay()->toDateTimeString(), now()->addWeek()->toDateTimeString());

    $fx['period']->update(['is_force_closed' => true]);

    // Force close mengalahkan tanggal per detail — sumbu yang berbeda (BR-SCH-07).
    expect($fx['detail']->fresh()->isWithinDeadline())->toBeFalse()
        ->and($fx['detail']->fresh()->isWithinDeadline(DeadlineType::Evaluation))->toBeFalse();
});

it('[FE-WINDOW-10] revisi digerbangi Batas Revisi, bukan Batas Submit', function () {
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $submission->forceFill(['status' => NeedsRevision::class, 'is_submitted' => true])->save();

    $fx['detail']->submissionDate->update(['datetime' => now()->subDay()]);
    $fx['detail']->update(['revision_date_id' => windowDate($fx['period'], 'Batas Revisi', now()->addWeek()->toDateTimeString())->id]);

    $payload = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->fresh()->updated_at->toISOString());

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->travel(8)->days();
    $payload['last_updated_at'] = $submission->fresh()->updated_at->toISOString();

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), $payload)
        ->assertForbidden();
});

// Catatan: tests/Unit/FormPhaseDetailTest.php dihapus dan digantikan berkas
// ini. Versi unit itu merangkai periode pada `formPhase` — relasi yang tidak
// pernah bisa ada, karena tabel `form_phases` tidak punya kolom
// `submission_period_id`. Ia lulus justru karena stub-nya sendiri, sementara
// kode produksinya selalu fatal begitu benar-benar dipanggil.
//
// Pelajarannya: stub yang merangkai relasi dengan tangan bisa membuktikan
// sesuatu yang mustahil. Di sini semuanya record sungguhan.
