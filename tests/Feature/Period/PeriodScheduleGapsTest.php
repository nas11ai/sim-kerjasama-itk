<?php

// tests/Feature/Period/PeriodScheduleGapsTest.php
//
// BR-FE-26 (ADR 0019) — periode menjaga tanggal yang dibutuhkan langkah fase tertautnya:
// celahnya terlihat di kartu fase, tanggal yang dibutuhkan tidak terhapus saat periode direvisi,
// dan Batas Evaluasi yang digeser ikut menggeser salinannya di penugasan (K8e).

use App\Actions\Period\ExtendSubmissionDates;
use App\Actions\Period\ReviseSubmissionPeriod;
use App\Data\Request\PeriodDateInput;
use App\Data\Request\SubmissionPeriodInput;
use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Form\PhaseLibrary;
use App\Queries\Period\PeriodScheduleGaps;
use Illuminate\Support\Carbon;

function gapPhase(string $title = 'Fase Celah'): FormPhase
{
    return FormPhase::create(['title' => $title, 'is_active' => true]);
}

it('[FE-WINDOW-08] mendaftar celah Batas Evaluasi langkah yang direview', function () {
    $phase = gapPhase();
    $step = scheduleStep($phase, needsReview: true);
    $period = schedulePeriod($phase, 'Periode A');
    calendarDate($period, DeadlineType::Submit, now()->addWeek());

    expect(PeriodScheduleGaps::run($period))->toBe([
        ['phase' => 'Fase Celah', 'step' => 1, 'label' => 'Batas Evaluasi'],
    ]);

    $form = $step->formAccessControl?->form;
    expect($form)->not->toBeNull();
    $card = collect(PhaseLibrary::run($form))->firstWhere('id', $phase->id);

    expect($card?->deadlineGaps)->toBe([['period' => 'Periode A', 'count' => 1]]);
});

it('mendaftar tanggal kerja yang hilang, tetapi tidak Batas Evaluasi langkah yang tidak direview', function () {
    $phase = gapPhase();
    scheduleStep($phase, cycle: 1, needsReview: false, order: 3);
    $period = schedulePeriod($phase, 'Periode B');

    expect(PeriodScheduleGaps::run($period))->toBe([
        ['phase' => 'Fase Celah', 'step' => 3, 'label' => 'Batas Laporan Monev I'],
    ]);
});

it('menghitung tanggal, bukan langkah: dua langkah bersiklus sama berbagi satu celah', function () {
    $phase = gapPhase();
    $step = scheduleStep($phase, cycle: 1, needsReview: false, order: 1);
    scheduleStep($phase, cycle: 1, needsReview: false, order: 2);
    schedulePeriod($phase, 'Periode C');

    $form = $step->formAccessControl?->form;
    expect($form)->not->toBeNull();
    $card = collect(PhaseLibrary::run($form))->firstWhere('id', $phase->id);

    expect($card?->deadlineGaps)->toBe([['period' => 'Periode C', 'count' => 1]]);
});

it('tidak menghapus tanggal yang dibutuhkan fase tertaut', function () {
    $phase = gapPhase();
    scheduleStep($phase, cycle: 1, needsReview: false);
    $period = schedulePeriod($phase, 'Periode D');
    $submit = calendarDate($period, DeadlineType::Submit, now()->addWeek());
    $monev = calendarDate($period, DeadlineType::Submit, now()->addMonth(), cycle: 1);
    $unneeded = calendarDate($period, DeadlineType::Submit, now()->addMonths(2), cycle: 2);

    ExtendSubmissionDates::run($period, [
        new PeriodDateInput(type: DeadlineType::Submit, cycle: null, date: $submit->datetime->toDateTimeString()),
    ]);

    expect(SubmissionDate::query()->whereKey($monev->id)->exists())->toBeTrue()
        ->and(SubmissionDate::query()->whereKey($unneeded->id)->exists())->toBeFalse()
        ->and(SubmissionDate::query()->whereKey($submit->id)->exists())->toBeTrue();
});

/** Penugasan form evaluasi langkah `$step` pada pengajuan baru di `$period`, ber-due_date `$due`. */
function gapAssignment(FormPhaseDetail $step, SubmissionPeriod $period, Carbon $due): ReviewerFormAssignment
{
    $form = ReviewEvaluationForm::query()->firstOrCreate(
        ['form_phase_detail_id' => $step->id],
        ['title' => 'Form Substansi', 'is_active' => true, 'is_required' => true, 'order' => 1],
    );
    $seat = SubmissionReviewer::create([
        'form_submission_id' => scheduledSubmission($step, $period)->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    return ReviewerFormAssignment::create([
        'submission_reviewer_id' => $seat->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'due_date' => $due,
    ]);
}

it('menggeser due_date penugasan saat Batas Evaluasi periode diperpanjang', function () {
    $phase = gapPhase();
    $step = scheduleStep($phase, needsReview: true);
    $periodA = schedulePeriod($phase, 'Periode A');
    $periodB = schedulePeriod($phase, 'Periode B');
    $old = Carbon::parse('2026-12-10 23:59:00');
    $new = Carbon::parse('2026-12-20 23:59:00');
    $submitA = calendarDate($periodA, DeadlineType::Submit, '2026-12-01 23:59:00');
    calendarDate($periodA, DeadlineType::Evaluation, $old);
    calendarDate($periodB, DeadlineType::Evaluation, $old);

    $own = gapAssignment($step, $periodA, $old);
    $otherPeriod = gapAssignment($step, $periodB, $old);
    $manual = gapAssignment($step, $periodA, Carbon::parse('2026-12-31 23:59:00'));

    ExtendSubmissionDates::run($periodA, [
        new PeriodDateInput(DeadlineType::Submit, null, $submitA->datetime->toDateTimeString()),
        new PeriodDateInput(DeadlineType::Evaluation, null, $new->toDateTimeString()),
    ]);

    expect($own->fresh()?->due_date?->toDateTimeString())->toBe('2026-12-20 23:59:00')
        ->and($otherPeriod->fresh()?->due_date?->toDateTimeString())->toBe('2026-12-10 23:59:00')
        ->and($manual->fresh()?->due_date?->toDateTimeString())->toBe('2026-12-31 23:59:00');
});

it('revisi periode tidak menghapus tanggal yang dibaca langkah fase yang tetap ditautkan', function () {
    $phase = gapPhase();
    scheduleStep($phase, cycle: 1, needsReview: false);
    $period = schedulePeriod($phase, 'Periode Revisi');
    $submit = calendarDate($period, DeadlineType::Submit, now()->addWeek());
    $monev = calendarDate($period, DeadlineType::Submit, now()->addMonth(), cycle: 1);

    ReviseSubmissionPeriod::run($period, new SubmissionPeriodInput(
        name: 'Periode Revisi',
        dates: [new PeriodDateInput(DeadlineType::Submit, null, $submit->datetime->toDateTimeString())],
        formPhaseIds: [$phase->id],
    ), User::factory()->create());

    expect(SubmissionDate::query()->whereKey($monev->id)->exists())->toBeTrue();
});

it('tanggal yang dibaca pengajuan periode tetap terjaga walau fasenya dilepas dari periode', function () {
    $phase = gapPhase();
    $pengajuan = scheduleStep($phase, needsReview: false);
    scheduleStep($phase, cycle: 2, needsReview: false, order: 5);
    $period = schedulePeriod($phase, 'Angkatan 2025');
    $submit = calendarDate($period, DeadlineType::Submit, now()->subMonth());
    $monev2 = calendarDate($period, DeadlineType::Submit, now()->addMonth(), cycle: 2);
    scheduledSubmission($pengajuan, $period);

    // Operator melepas fase dari periode lama, lalu membuang Batas Laporan Monev II.
    ReviseSubmissionPeriod::run($period, new SubmissionPeriodInput(
        name: 'Angkatan 2025',
        dates: [new PeriodDateInput(DeadlineType::Submit, null, $submit->datetime->toDateTimeString())],
        formPhaseIds: [],
    ), User::factory()->create());

    expect(SubmissionDate::query()->whereKey($monev2->id)->exists())->toBeTrue();
});

it('celah jadwal tetap tampil di periode yang sudah tutup bila fase itu masih punya pengajuannya', function () {
    $phase = gapPhase();
    $pengajuan = scheduleStep($phase, needsReview: false);
    scheduleStep($phase, cycle: 2, needsReview: false, order: 5);
    $period = schedulePeriod($phase, 'Angkatan Lama');
    calendarDate($period, DeadlineType::Submit, now()->subMonth());
    $closedEmpty = schedulePeriod($phase, 'Periode Lewat Kosong');
    calendarDate($closedEmpty, DeadlineType::Submit, now()->subMonth());
    scheduledSubmission($pengajuan, $period);

    $form = $pengajuan->formAccessControl?->form;
    expect($form)->not->toBeNull();
    $card = collect(PhaseLibrary::run($form))->firstWhere('id', $phase->id);

    expect($card?->deadlineGaps)->toBe([['period' => 'Angkatan Lama', 'count' => 1]]);
});
