<?php

// tests/Feature/FormEngine/StructureDeletionTest.php
//
// BR-FE-19 s/d BR-FE-22 — Form, tahapnya, periodenya, dan form evaluasinya
// adalah kerangka yang memayungi setiap pengajuan.
//
// Sebelum aturan ini, FormController::bulkDelete menjalankan
// Form::whereIn(...)->delete() tanpa penjagaan apa pun, dan
// form_submissions.form_id ber-cascade. Satu klik Admin memusnahkan seluruh
// riwayat sebuah form beserta anggota, anggaran, evaluasi, dan luarannya.

use App\Actions\Form\DeleteFormPhase;
use App\Actions\Form\SetFormActive;
use App\Actions\Form\SetFormPhaseActive;
use App\Actions\FormSubmission\StartSubmission;
use App\Actions\Period\DeleteSubmissionPeriod;
use App\Actions\Period\ExtendSubmissionDates;
use App\Enums\DeadlineType;
use App\Models\Form;
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
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function adminPenghapus(): User
{
    return User::factory()->create(['is_active' => true]);
}

function fasePenghapusan(): FormPhase
{
    return FormPhase::create([
        'title' => 'Tahap '.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);
}

function formEvaluasiPenghapusan(): ReviewEvaluationForm
{
    return evaluationFormOn(FormPhaseDetail::factory()->create(), [], title: 'Lembar '.fake()->unique()->numerify('####'));
}

// ---------- BR-FE-19: Form ----------

it('[FE-DELETE-01] Form yang punya pengajuan tidak bisa dihapus', function () {
    $form = Form::factory()->create();
    FormSubmission::factory()->count(3)->create(['form_id' => $form->id]);

    expect(fn () => $form->delete())->toThrow(ValidationException::class)
        ->and(Form::query()->whereKey($form->id)->exists())->toBeTrue()
        // Yang paling penting: pengajuannya tidak ikut lenyap.
        ->and(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(3);
});

it('[FE-DELETE-02] draft pun menghalangi penghapusan Form', function () {
    $form = Form::factory()->create();
    FormSubmission::factory()->create(['form_id' => $form->id, 'status' => Draft::class]);

    expect(fn () => $form->delete())->toThrow(ValidationException::class);
});

it('[FE-DELETE-03] pengajuan berstatus terminal tetap menghalangi', function (string $state) {
    $form = Form::factory()->create();
    FormSubmission::factory()->create(['form_id' => $form->id, 'status' => $state]);

    expect(fn () => $form->delete())->toThrow(ValidationException::class);
})->with([Approved::class, Rejected::class, Withdrawn::class]);

it('[FE-DELETE-04] Form tanpa satu pun pengajuan bisa dihapus', function () {
    $form = Form::factory()->create();

    $form->delete();

    expect(Form::query()->whereKey($form->id)->exists())->toBeFalse();
});

it('[FE-DELETE-05] Form yang masih dipakai dinonaktifkan, bukan dihapus', function () {
    $fx = wizardFixture();
    $form = $fx['form'];
    $form->update(['is_active' => true]);
    FormSubmission::factory()->count(2)->create(['form_id' => $form->id]);

    SetFormActive::run($form, false, adminPenghapus());

    expect($form->fresh()->is_active)->toBeFalse()
        ->and(fn () => StartSubmission::run($form->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(ValidationException::class)
        ->and(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(2);
});

it('[FE-DELETE-05] penghapusan massal ikut dijaga, bukan hanya penghapusan satuan', function () {
    $form = Form::factory()->create();
    FormSubmission::factory()->create(['form_id' => $form->id]);

    // Jalur inilah yang dulu bebas: query builder tidak memicu event model.
    expect(fn () => Form::whereIn('id', [$form->id])->get()->each(fn (Form $f) => $f->delete()))
        ->toThrow(ValidationException::class)
        ->and(Form::query()->whereKey($form->id)->exists())->toBeTrue();
});

// ---------- BR-FE-20: tahap dan detailnya ----------

it('[FE-DELETE-06] FormPhase yang dirujuk pengajuan tidak bisa dihapus', function () {
    $phase = fasePenghapusan();
    $detail = FormPhaseDetail::factory()->create(['form_phase_id' => $phase->id]);
    FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    expect(fn () => DeleteFormPhase::run($phase, adminPenghapus()))
        ->toThrow(ValidationException::class)
        ->and(FormPhase::query()->whereKey($phase->id)->exists())->toBeTrue()
        // Detailnya juga harus selamat: versi lama menghapusnya lebih dulu dan
        // baru kandas di foreign key.
        ->and(FormPhaseDetail::query()->whereKey($detail->id)->exists())->toBeTrue();
});

it('[FE-DELETE-07] FormPhaseDetail yang dirujuk pengajuan tidak bisa dihapus', function () {
    $detail = FormPhaseDetail::factory()->create();
    FormSubmission::factory()->count(3)->create(['form_phase_detail_id' => $detail->id]);

    expect(fn () => $detail->delete())->toThrow(ValidationException::class)
        ->and(FormPhaseDetail::query()->whereKey($detail->id)->exists())->toBeTrue();
});

it('[FE-DELETE-08] penolakan menyebut jumlah pengajuan yang menghalangi', function () {
    $detail = FormPhaseDetail::factory()->create();
    FormSubmission::factory()->count(3)->create(['form_phase_detail_id' => $detail->id]);

    try {
        $detail->delete();
        $this->fail('Penghapusan seharusnya ditolak.');
    } catch (ValidationException $e) {
        $pesan = implode(' ', $e->validator->errors()->all());

        // Angkanya yang memberi tahu Admin apakah ia salah memilih baris atau
        // memang harus menonaktifkan. Dan ia harus aturan domain, bukan
        // SQLSTATE yang bocor dari basis data.
        expect($pesan)->toContain('3')
            ->and($pesan)->not->toContain('SQLSTATE');
    }
});

it('[FE-DELETE-09] FormPhase tanpa pengajuan bisa dihapus', function () {
    $phase = fasePenghapusan();
    FormPhaseDetail::factory()->create(['form_phase_id' => $phase->id]);

    DeleteFormPhase::run($phase, adminPenghapus());

    expect(FormPhase::query()->whereKey($phase->id)->exists())->toBeFalse()
        ->and(FormPhaseDetail::query()->where('form_phase_id', $phase->id)->exists())->toBeFalse();
});

it('[FE-DELETE-15] tahap yang masih dipakai dinonaktifkan, bukan dihapus', function () {
    $fx = wizardFixture();
    FormSubmission::factory()->count(2)->create(['form_phase_detail_id' => $fx['detail']->id]);

    SetFormPhaseActive::run($fx['phase'], false, adminPenghapus());

    // BR-FE-20 — tahap berhenti menerima pengajuan baru, pengajuan lama tetap terbaca.
    try {
        StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
        $this->fail('Tahap nonaktif seharusnya menolak pengajuan baru.');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['form_phase_id' => ['Tahap ini sudah tidak menerima pengajuan baru.']]);
    }

    expect(FormSubmission::query()->where('form_phase_detail_id', $fx['detail']->id)->count())->toBe(2);
});

// ---------- BR-FE-21: periode ----------

it('[FE-DELETE-10] SubmissionPeriod yang punya pengajuan tidak bisa dihapus', function () {
    $period = SubmissionPeriod::factory()->create();
    FormSubmission::factory()->count(2)->create(['submission_period_id' => $period->id]);

    expect(fn () => DeleteSubmissionPeriod::run($period, adminPenghapus()))
        ->toThrow(ValidationException::class)
        ->and(SubmissionPeriod::query()->whereKey($period->id)->exists())->toBeTrue();
});

it('penghapusan periode yang ditolak tidak meninggalkan jejak audit — BR-RPT-09', function () {
    // Audit dicatat sebelum delete() memicu hook `deleting`. Tanpa guard milik
    // Action, hook-lah yang menolak — dan entri "dihapus" sudah telanjur tertulis
    // untuk penghapusan yang tidak pernah terjadi.
    $period = SubmissionPeriod::factory()->create();
    FormSubmission::factory()->create(['submission_period_id' => $period->id]);

    expect(fn () => DeleteSubmissionPeriod::run($period, adminPenghapus()))
        ->toThrow(ValidationException::class);

    expect(Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $period->getMorphClass())
        ->where('subject_id', $period->id)
        ->where('description', 'Periode pengajuan dihapus')
        ->exists())->toBeFalse();
});

it('[FE-DELETE-11] tanggal periode yang dibaca langkah fase tertaut tidak ikut terhapus', function () {
    $phase = FormPhase::create(['title' => 'Proposal Awal', 'is_active' => true]);
    scheduleStep($phase, needsReview: false);
    $period = schedulePeriod($phase, 'Gelombang Uji');
    $tanggal = calendarDate($period, DeadlineType::Submit, now()->addMonth());

    // BR-FE-26 (ADR 0019) — langkah membaca Batas Submit dari kalender periode ini; menghapusnya
    // menutup langkah itu jauh dari tempat Operator menyunting periode.
    ExtendSubmissionDates::run($period, []);

    expect(SubmissionDate::query()->whereKey($tanggal->id)->exists())->toBeTrue();
});

it('[FE-DELETE-12] periode tanpa pengajuan bisa dihapus', function () {
    $period = SubmissionPeriod::factory()->create();

    DeleteSubmissionPeriod::run($period, adminPenghapus());

    expect(SubmissionPeriod::query()->whereKey($period->id)->exists())->toBeFalse();
});

// ---------- BR-FE-22: form evaluasi ----------

it('[FE-DELETE-13] form evaluasi yang pernah ditugaskan tidak bisa dihapus', function () {
    $form = formEvaluasiPenghapusan();
    $reviewer = Reviewer::factory()->create();
    $penugasan = SubmissionReviewer::create([
        'form_submission_id' => FormSubmission::factory()->create()->id,
        'reviewer_id' => $reviewer->id,
        'assigned_at' => now(),
    ]);
    ReviewerFormAssignment::create([
        'submission_reviewer_id' => $penugasan->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
    ]);

    expect(fn () => $form->delete())->toThrow(ValidationException::class)
        ->and(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeTrue();
});

it('[FE-DELETE-14] form evaluasi yang belum pernah dipakai bisa dihapus', function () {
    // BR-REV-18 — form pustaka yang tak terpasang di langkah mana pun, versinya ikut ter-cascade.
    $form = ReviewEvaluationForm::create(['title' => 'Lembar Lepas']);
    publishVersion($form, [['type' => 'Text', 'label' => 'Catatan']]);

    $form->delete();

    expect(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeFalse();
});

it('form evaluasi yang masih terpasang di langkah tidak bisa dihapus', function () {
    $form = formEvaluasiPenghapusan();

    expect(fn () => $form->delete())->toThrow(ValidationException::class, '1 pemasangan di langkah')
        ->and(ReviewEvaluationForm::query()->whereKey($form->id)->exists())->toBeTrue();
});
