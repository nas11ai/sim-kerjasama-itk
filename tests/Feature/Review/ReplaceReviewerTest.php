<?php

// tests/Feature/Review/ReplaceReviewerTest.php
//
// BR-REV-10 — penggantian reviewer. Record lama ditandai `replaced`, bukan
// dihapus: menghapus evaluasi lama berarti menghapus jejak kenapa sebuah
// keputusan diambil.
//
// Reviewer pengganti memulai bersih (`evaluation_status = pending`), dan
// BR-REV-15 memberinya warisan ulir diskusi yang masih terbuka — kalau tidak,
// summary itu tidak akan pernah bisa ditutup: penulis lama tidak berwenang,
// pengganti tidak merasa berhak, dan submission terkunci di NEEDS_REVISION.

use App\Actions\Review\ReplaceReviewer;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Illuminate\Validation\ValidationException;

/** @return array{0: FormSubmission, 1: Reviewer, 2: SubmissionReviewer} */
function replaceFixture(string $evaluation = 'completed'): array
{
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'status' => UnderReview::class,
    ]);

    $outgoing = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    $assignment = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $outgoing->id,
        'evaluation_status' => $evaluation,
        'status' => 'active',
    ]);

    return [$submission, $outgoing, $assignment];
}

function incomingReviewer(): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

it('[REV-REASSIGN-01] record lama ditandai replaced, bukan dihapus', function () {
    [$submission, $outgoing, $assignment] = replaceFixture();

    ReplaceReviewer::run($submission, $outgoing, incomingReviewer());

    expect($assignment->fresh()->status)->toBe('replaced')
        ->and(SubmissionReviewer::find($assignment->id))->not->toBeNull();
});

it('[REV-REASSIGN-02] evaluasi reviewer lama tetap terbaca sebagai riwayat', function () {
    [$submission, $outgoing, $assignment] = replaceFixture();

    ReplaceReviewer::run($submission, $outgoing, incomingReviewer());

    // Menghapusnya menghilangkan alasan di balik keputusan yang sudah diambil.
    expect($assignment->fresh()->evaluation_status)->toBe('completed');
});

it('[REV-REASSIGN-03] reviewer pengganti mendapat penugasan bersih', function () {
    [$submission, $outgoing] = replaceFixture();
    $incoming = incomingReviewer();

    $fresh = ReplaceReviewer::run($submission, $outgoing, $incoming);

    expect($fresh->evaluation_status)->toBe('pending')
        ->and($fresh->status)->toBe('active')
        ->and($fresh->reviewer_id)->toBe($incoming->id);
});

it('[REV-DISCUSS-06] ulir terbuka diwarisi pengganti, penulis asli tetap terbaca', function () {
    [$submission, $outgoing] = replaceFixture();
    $incoming = incomingReviewer();

    $summary = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $outgoing->id,
        'status' => 'open',
        'summary_notes' => 'Metodologi perlu diperjelas.',
    ]);

    ReplaceReviewer::run($submission, $outgoing, $incoming);

    // BR-REV-15 — pengganti yang berwenang menutupnya, tapi isi catatannya
    // tetap milik penulis asli.
    expect($summary->fresh()->reviewer_id)->toBe($incoming->id)
        ->and($summary->fresh()->summary_notes)->toContain('Metodologi perlu diperjelas');
});

it('[REV-DISCUSS-06] ulir yang sudah resolved tidak ikut dialihkan', function () {
    [$submission, $outgoing] = replaceFixture();

    $done = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $outgoing->id,
        'status' => 'resolved',
        'summary_notes' => 'Sudah selesai.',
    ]);

    ReplaceReviewer::run($submission, $outgoing, incomingReviewer());

    // Yang sudah ditutup tidak menuntut siapa pun — mengalihkannya justru
    // mengaburkan siapa yang dulu menutupnya.
    expect($done->fresh()->reviewer_id)->toBe($outgoing->id);
});

it('[REV-REASSIGN-01] mengganti dengan reviewer yang sudah bertugas ditolak', function () {
    [$submission, $outgoing] = replaceFixture();

    $existing = incomingReviewer();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $existing->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    ReplaceReviewer::run($submission, $outgoing, $existing);
})->throws(ValidationException::class);

it('[REV-DISCUSS-06] pengganti menutup ulir warisan lewat resolve', function () {
    [$submission, $outgoing] = replaceFixture();
    $incoming = incomingReviewer();

    $summary = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $outgoing->id,
        'status' => 'open',
        'summary_notes' => 'Metodologi perlu diperjelas.',
    ]);

    ReplaceReviewer::run($submission, $outgoing, $incoming);

    // BR-REV-15, BR-REV-10 — guard penugasan aktif tidak boleh menolak pengganti.
    $this->actingAs(User::findOrFail($incoming->user_id))
        ->post(route('review-summaries.resolve', $summary))
        ->assertRedirect();

    expect($summary->fresh()?->status)->toBe('resolved');
});
