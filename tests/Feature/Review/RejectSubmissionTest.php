<?php

// tests/Feature/Review/RejectSubmissionTest.php
//
// BR-REV-07 dan BR-REV-14 — REJECTED hanya dicapai lewat aksi eksplisit
// Operator, tidak pernah otomatis (ADR 0006).
//
// Alasannya wajib: penolakan adalah keputusan yang menutup pengajuan
// selamanya, dan peneliti berhak tahu kenapa. Penolakan tanpa alasan
// membuat mekanisme banding jadi mustahil.

use App\Actions\Review\RejectSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function rejectOperator(): User
{
    Permission::findOrCreate('submissions.view-all', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    return $operator;
}

/**
 * BR-REV-07 — Tolak menunggu pengajuan siap diputuskan (ADR 0018): fixture-nya `readyForDecision()`,
 * kecuali status lain diminta.
 *
 * @return array{0: User, 1: FormSubmission}
 */
function rejectFixture(?string $state = null): array
{
    $submission = $state === null
        ? readyForDecision()
        : FormSubmission::factory()->create([
            'submitted_by' => User::factory()->create()->id,
            'scheme_id' => null,
            'status' => $state,
        ]);

    return [$submission->submittedBy, $submission];
}

it('[REV-OUTCOME-07] Operator menolak lewat aksi eksplisit', function () {
    [, $submission] = rejectFixture();

    RejectSubmission::run($submission, rejectOperator(), 'Metodologi tidak memadai untuk skala yang diajukan.');

    expect($submission->fresh()->status)->toBeInstanceOf(Rejected::class);
});

it('[REV-OUTCOME-07] penolakan tanpa alasan ditolak', function () {
    [, $submission] = rejectFixture();

    RejectSubmission::run($submission, rejectOperator(), '   ');
})->throws(ValidationException::class);

it('[REV-OUTCOME-07] alasan penolakan tersimpan dan terbaca', function () {
    [, $submission] = rejectFixture();

    RejectSubmission::run($submission, rejectOperator(), 'Anggaran melebihi kewajaran.');

    expect($submission->fresh()->rejection_reason)->toContain('kewajaran');
});

it('[REV-OUTCOME-07] researcher tidak bisa menolak pengajuan', function () {
    [$owner, $submission] = rejectFixture();

    RejectSubmission::run($submission, $owner, 'Alasan apa pun.');
})->throws(AuthorizationException::class);

it('[REV-OUTCOME-07] pengajuan yang sudah disetujui tidak bisa ditolak', function () {
    [, $submission] = rejectFixture(Approved::class);

    // State machine tidak mengizinkan APPROVED → REJECTED; keputusan yang
    // sudah diambil dibatalkan lewat penarikan, bukan penolakan susulan.
    RejectSubmission::run($submission, rejectOperator(), 'Berubah pikiran.');
})->throws(AuthorizationException::class);
