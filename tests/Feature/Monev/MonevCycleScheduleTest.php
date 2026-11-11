<?php

// tests/Feature/Monev/MonevCycleScheduleTest.php
//
// BR-FE-26, BR-MON-05 (ADR 0019) — siklus monev dan Upload Luaran membaca kalender periode
// pengajuan induk. Menautkan fase yang sama ke periode baru tidak mengubah tanggal angkatan lama.

use App\Data\View\Monev\MonevCycleData;
use App\Enums\DeadlineType;
use App\Enums\MonevCycleState;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Queries\Monev\MonevCycles;
use App\Queries\ResearchOutput\OutputWriteAccess;
use App\States\Submission\Approved;
use Illuminate\Support\Carbon;

/** @return array<string, MonevCycleData> siklus induk per nama */
function cyclesByName(FormSubmission $parent): array
{
    return collect(MonevCycles::run($parent))->keyBy(fn (MonevCycleData $c): string => $c->name)->all();
}

beforeEach(function () {
    $this->phase = FormPhase::create(['title' => 'Penelitian DIPA', 'is_active' => true]);
    $this->pengajuan = scheduleStep($this->phase, order: 1);
    $this->period2025 = schedulePeriod($this->phase, 'Periode 2025');
    $this->approved = fn (): FormSubmission => scheduledSubmission($this->pengajuan, $this->period2025, [
        'status' => Approved::class, 'is_submitted' => true,
    ]);
});

it('[FE-WINDOW-15] menyiapkan periode baru tidak mengubah tanggal monev angkatan lama', function () {
    scheduleStep($this->phase, cycle: 1, needsReview: false, order: 3);
    scheduleStep($this->phase, cycle: 2, needsReview: false, order: 5);
    calendarDate($this->period2025, DeadlineType::Submit, '2026-06-01 23:59:00', cycle: 2);
    $parent = ($this->approved)();

    $period2026 = schedulePeriod($this->phase, 'Periode 2026');
    calendarDate($period2026, DeadlineType::Submit, '2027-06-01 23:59:00', cycle: 2);

    expect(cyclesByName($parent)['Monev II']->deadline)
        ->toBe(Carbon::parse('2026-06-01 23:59:00')->toIso8601String());
});

it('[FE-WINDOW-11] siklus memakai tanggal siklusnya sendiri', function () {
    scheduleStep($this->phase, cycle: 1, needsReview: false, order: 3);
    scheduleStep($this->phase, cycle: 2, needsReview: false, order: 5);
    calendarDate($this->period2025, DeadlineType::Submit, '2026-03-01 23:59:00', cycle: 1);
    calendarDate($this->period2025, DeadlineType::Submit, '2026-09-01 23:59:00', cycle: 2);

    $cycles = cyclesByName(($this->approved)());

    expect($cycles['Monev I']->deadline)->toBe(Carbon::parse('2026-03-01 23:59:00')->toIso8601String())
        ->and($cycles['Monev II']->deadline)->toBe(Carbon::parse('2026-09-01 23:59:00')->toIso8601String());
});

it('menutup siklus yang jadwalnya belum ada di periode induk', function () {
    scheduleStep($this->phase, cycle: 2, needsReview: false, order: 5);

    $cycle = cyclesByName(($this->approved)())['Monev II'];

    expect($cycle->state)->toBe(MonevCycleState::Closed)
        ->and($cycle->blockedReason)->toBe('Jadwal Monev II belum diatur di periode ini. Hubungi Operator LPPM.')
        ->and($cycle->deadline)->toBeNull();
});

it('tidak menghitung Upload Luaran sebagai siklus monev', function () {
    scheduleStep($this->phase, cycle: 1, needsReview: false, order: 3);
    scheduleStep($this->phase, needsReview: false, order: 7, type: DeadlineType::Output);

    expect(array_keys(cyclesByName(($this->approved)())))->toBe(['Monev I'])
        ->and(MonevCycles::details(($this->approved)()))->toHaveCount(1);
});

it('menggerbangi Upload Luaran dengan Batas Akhir periode induk', function () {
    $fx = outputUploadFixture(now()->subDay()->toDateTimeString());
    $later = schedulePeriod($fx['phase'], 'Periode Berikutnya');
    calendarDate($later, DeadlineType::Output, now()->addMonth());
    $inLater = scheduledSubmission($fx['detail'], $later, [
        'form_id' => $fx['form']->id, 'submitted_by' => $fx['user']->id,
        'status' => Approved::class, 'is_submitted' => true,
    ]);

    $old = OutputWriteAccess::for($fx['user'], $fx['submission']);
    $new = OutputWriteAccess::for($fx['user'], $inLater);

    expect($old->canWrite)->toBeFalse()
        ->and($old->blockedReason)->toBe(OutputWriteAccess::PAST_DEADLINE)
        ->and($old->deadline)->toBe($fx['period']->submissionDates()->where('type', 'output')->sole()->datetime->toIso8601String())
        ->and($new->canWrite)->toBeTrue();
});
