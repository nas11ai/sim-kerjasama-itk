<?php

// tests/Feature/FormEngine/PeriodScheduleTest.php
//
// BR-FE-26 (ADR 0019) — langkah fase menyebut jenis tenggat + siklus; tanggalnya dibaca dari
// kalender periode milik pengajuan. Satu fase dipakai dua periode dengan tanggal masing-masing.

use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\ReviewEvaluationForm;
use App\Queries\Review\EvaluationDueDate as ReviewEvaluationDueDate;
use App\Queries\Submission\EvaluationDueDate as FormEvaluationDueDate;
use App\States\Submission\NeedsRevision;
use App\Support\PhaseAccessGate;

beforeEach(function () {
    $this->phase = FormPhase::create(['title' => 'Penelitian DIPA', 'is_active' => true]);
    $this->step = scheduleStep($this->phase);
    $this->periodA = schedulePeriod($this->phase, 'Periode 2025');
    $this->periodB = schedulePeriod($this->phase, 'Periode 2026');
});

it('[FE-WINDOW-14] menggerbangi tiap pengajuan dengan tanggal periodenya', function () {
    calendarDate($this->periodA, DeadlineType::Submit, now()->subDay());
    calendarDate($this->periodB, DeadlineType::Submit, now()->addWeek());

    $inA = scheduledSubmission($this->step, $this->periodA);
    $inB = scheduledSubmission($this->step, $this->periodB);

    expect(PhaseAccessGate::isOpenFor($inA, $this->step))->toBeFalse()
        ->and(PhaseAccessGate::isOpenFor($inB, $this->step))->toBeTrue();
});

it('[FE-WINDOW-16] menutup langkah yang tanggal kerjanya tidak ada di kalender', function () {
    calendarDate($this->periodB, DeadlineType::Submit, now()->addWeek());

    expect($this->step->isWithinDeadline($this->periodA))->toBeFalse()
        ->and($this->step->isWithinDeadline($this->periodA, DeadlineType::Evaluation))->toBeTrue()
        ->and($this->step->isWithinDeadline($this->periodB))->toBeTrue();
});

it('[FE-WINDOW-04] dua langkah bersiklus sama membaca satu tanggal', function () {
    $first = scheduleStep($this->phase, cycle: 1, order: 2);
    $second = scheduleStep($this->phase, cycle: 1, needsReview: false, order: 3);
    $monev = calendarDate($this->periodA, DeadlineType::Submit, now()->addMonth(), cycle: 1);
    calendarDate($this->periodA, DeadlineType::Submit, now()->addWeek());

    expect($this->periodA->deadlineFor($first, DeadlineType::Submit)?->id)->toBe($monev->id)
        ->and($this->periodA->deadlineFor($second, DeadlineType::Submit)?->id)->toBe($monev->id);
});

it('kembali ke Batas Submit periode saat Batas Revisi tidak ada', function () {
    $submit = calendarDate($this->periodA, DeadlineType::Submit, now()->addWeek());
    calendarDate($this->periodB, DeadlineType::Revision, now()->addMonth());

    $revising = scheduledSubmission($this->step, $this->periodA, ['status' => NeedsRevision::class]);

    expect(PhaseAccessGate::deadlineFor($revising, $this->step)?->id)->toBe($submit->id)
        ->and(PhaseAccessGate::isOpenFor($revising, $this->step))->toBeTrue();
});

it('[FE-WINDOW-17] force close periode A tidak menutup pengajuan periode B', function () {
    calendarDate($this->periodA, DeadlineType::Submit, now()->addWeek());
    calendarDate($this->periodB, DeadlineType::Submit, now()->addWeek());
    $this->periodA->update(['is_force_closed' => true]);

    $inA = scheduledSubmission($this->step, $this->periodA);
    $inB = scheduledSubmission($this->step, $this->periodB);

    expect(PhaseAccessGate::isOpenFor($inA, $this->step))->toBeFalse()
        ->and(PhaseAccessGate::isOpenFor($inB, $this->step))->toBeTrue();
});

it('memakai Batas Evaluasi periode pengajuan sebagai tenggat penugasan', function () {
    $dueA = now()->addDays(10)->startOfSecond();
    calendarDate($this->periodA, DeadlineType::Evaluation, $dueA);
    calendarDate($this->periodB, DeadlineType::Evaluation, now()->addDays(40));
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi', 'form_phase_detail_id' => $this->step->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);

    $inA = scheduledSubmission($this->step, $this->periodA);
    $undated = scheduledSubmission($this->step, schedulePeriod($this->phase, 'Periode Tanpa Tanggal'));

    expect(ReviewEvaluationDueDate::run($inA)->toDateTimeString())->toBe($dueA->toDateTimeString())
        ->and(FormEvaluationDueDate::run($form, $inA)?->format('Y-m-d H:i:s'))->toBe($dueA->toDateTimeString())
        ->and(ReviewEvaluationDueDate::run($undated)->isSameDay(now()->addWeek()))->toBeTrue()
        ->and(FormEvaluationDueDate::run($form, $undated))->toBeNull();
});
