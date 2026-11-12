<?php

// tests/Unit/EvaluationDueDateTest.php
//
// BR-FE-17 — tenggat sebuah FormPhaseDetail mengikat aktor detail itu sendiri.
// Evaluasi reviewer adalah tahap setelah submit, jadi ia wajib punya
// SubmissionDate sendiri; memakai ulang "Batas Submit" membuat jendela kerja
// reviewer nol atau negatif, dan memakai tanggal terakhir period memberi
// tenggat milik tahap yang sama sekali lain.

use App\Models\FormSubmission;
use App\Queries\Submission\EvaluationDueDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Pengajuan pada langkah pemilik form evaluasi, di periode fixture-nya (BR-FE-26). */
function dueDateSubmission(array $fx): FormSubmission
{
    return FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'form_phase_detail_id' => $fx['submitDetail']->id,
        'submission_period_id' => $fx['period']->id,
    ]);
}

it('mengambil tenggat dari kalender periode pengajuan pada langkah pemilik form — BR-FE-17', function () {
    $fx = phaseWithOwnEvaluationDeadline('Uji Tenggat Evaluasi');

    $dueDate = EvaluationDueDate::run(dueDateSubmission($fx));

    expect($dueDate)->not->toBeNull()
        ->and($dueDate->format('Y-m-d H:i:s'))->toBe($fx['dueEvaluation']->format('Y-m-d H:i:s'));
});

it('tidak memakai ulang Batas Submit milik peneliti', function () {
    $fx = phaseWithOwnEvaluationDeadline('Uji Bukan Batas Submit');

    expect(EvaluationDueDate::run(dueDateSubmission($fx))->format('Y-m-d H:i:s'))
        ->not->toBe($fx['dueSubmit']->format('Y-m-d H:i:s'));
});

it('tidak memakai tanggal terakhir period, yang milik tahap lain', function () {
    $fx = phaseWithOwnEvaluationDeadline('Uji Bukan Tanggal Terakhir');

    $latest = $fx['period']->submissionDates()->orderByDesc('datetime')->first();

    expect($latest)->not->toBeNull()
        ->and(EvaluationDueDate::run(dueDateSubmission($fx))->format('Y-m-d H:i:s'))
        ->not->toBe($latest->datetime->format('Y-m-d H:i:s'));
});

it('membaca periode pengajuan, bukan periode lain yang menautkan fase yang sama — ADR 0019', function () {
    // Periode B menautkan fase yang sama lewat pivot; pengajuan milik periode A tetap membaca
    // kalender periode A.
    $fx = phaseSharedByTwoPeriods('Uji Due Date Ambigu', needsReview: true);

    $form = evaluationFormOn($fx['detail'], [], title: 'Substansi');

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'submission_period_id' => $fx['periodA']->id,
    ]);

    expect(EvaluationDueDate::run($submission)->format('Y-m-d H:i:s'))
        ->toBe($fx['dueA']->format('Y-m-d H:i:s'))
        ->and(EvaluationDueDate::run($submission)->format('Y-m-d H:i:s'))
        ->not->toBe($fx['dueB']->format('Y-m-d H:i:s'));
});
