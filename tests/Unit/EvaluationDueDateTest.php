<?php

// tests/Unit/EvaluationDueDateTest.php
//
// BR-FE-17 — tenggat sebuah FormPhaseDetail mengikat aktor detail itu sendiri.
// Evaluasi reviewer adalah tahap setelah submit, jadi ia wajib punya
// SubmissionDate sendiri; memakai ulang "Batas Submit" membuat jendela kerja
// reviewer nol atau negatif, dan memakai tanggal terakhir period memberi
// tenggat milik tahap yang sama sekali lain.

use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Queries\Submission\EvaluationDueDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('mengambil tenggat dari detail yang memiliki form evaluasi — BR-FE-17', function () {
    $fx = phaseWithOwnEvaluationDeadline('Uji Tenggat Evaluasi');

    $dueDate = EvaluationDueDate::run($fx['evaluationForm']);

    expect($dueDate)->not->toBeNull()
        ->and($dueDate->format('Y-m-d H:i:s'))->toBe($fx['dueEvaluation']->format('Y-m-d H:i:s'));
});

it('tidak memakai ulang Batas Submit milik peneliti', function () {
    $fx = phaseWithOwnEvaluationDeadline('Uji Bukan Batas Submit');

    expect(EvaluationDueDate::run($fx['evaluationForm'])->format('Y-m-d H:i:s'))
        ->not->toBe($fx['dueSubmit']->format('Y-m-d H:i:s'));
});

it('tidak memakai tanggal terakhir period, yang milik tahap lain', function () {
    $fx = phaseWithOwnEvaluationDeadline('Uji Bukan Tanggal Terakhir');

    $latest = $fx['period']->submissionDates()->orderByDesc('datetime')->first();

    expect($latest)->not->toBeNull()
        ->and(EvaluationDueDate::run($fx['evaluationForm'])->format('Y-m-d H:i:s'))
        ->not->toBe($latest->datetime->format('Y-m-d H:i:s'));
});

it('tidak bergantung pada pivot submission_period_phases — ADR 0004', function () {
    // Jawabannya kini ditempuh lewat FK langsung, jadi period lain yang
    // kebetulan berbagi fase tidak bisa lagi menyeretnya.
    $fx = phaseSharedByTwoPeriods('Uji Due Date Ambigu', needsReview: true);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $fx['detail']->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'submission_period_id' => $fx['periodA']->id,
    ]);

    expect(EvaluationDueDate::run($form)->format('Y-m-d H:i:s'))
        ->toBe($fx['dueA']->format('Y-m-d H:i:s'))
        ->and(EvaluationDueDate::run($form)->format('Y-m-d H:i:s'))
        ->not->toBe($fx['dueB']->format('Y-m-d H:i:s'));
});
