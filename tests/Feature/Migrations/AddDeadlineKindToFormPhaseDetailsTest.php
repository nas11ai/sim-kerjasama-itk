<?php

// tests/Feature/Migrations/AddDeadlineKindToFormPhaseDetailsTest.php
//
// BR-FE-07 — langkah fase menyebut jenis tenggat + siklus, bukan menunjuk tanggal. BR-FE-26 —
// tanggalnya dibaca dari kalender periode pengajuan, jadi pengajuan bertahap wajib berperiode.

use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use Illuminate\Database\QueryException;

it('menolak pengajuan bertahap tanpa periode', function () {
    $step = FormPhaseDetail::factory()->create();

    FormSubmission::factory()->create([
        'form_phase_detail_id' => $step->id,
        'submission_period_id' => null,
    ]);
})->throws(QueryException::class, 'form_submissions_detail_has_period');

it('menerima pengajuan tanpa langkah walaupun tanpa periode', function () {
    $submission = FormSubmission::factory()->create(['submission_period_id' => null]);

    expect($submission->exists)->toBeTrue();
});

it('menyimpan jenis dan siklus langkah', function () {
    $step = FormPhaseDetail::factory()->create()->refresh();

    expect($step->deadline_type)->toBe(DeadlineType::Submit)
        ->and($step->deadline_cycle)->toBeNull();
});

it('menurunkan jenis dan siklus dari tanggal yang dirujuk selama tulis-ganda', function () {
    $period = SubmissionPeriod::create(['name' => 'Periode Tulis Ganda']);
    $monev = SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'cycle' => 2,
        'datetime' => now()->addMonth(),
        'submission_period_id' => $period->id,
    ]);

    $step = FormPhaseDetail::factory()->create(['submission_date_id' => $monev->id])->refresh();

    expect($step->deadline_type)->toBe(DeadlineType::Submit)
        ->and($step->deadline_cycle)->toBe(2);
});
