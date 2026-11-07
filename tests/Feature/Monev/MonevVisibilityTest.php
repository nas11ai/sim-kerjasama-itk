<?php

// tests/Feature/Monev/MonevVisibilityTest.php
//
// BR-MON-09 dan BR-MON-10 — hasil evaluasi monev dan pemberitahuannya.
//
// BR-MON-09 adalah pengecualian **eksplisit** atas BR-REV-12, dan sengaja
// sempit: monev formatif, jadi catatannya harus terbaca selagi kegiatannya
// masih bisa dikoreksi. [MON-REVIEW-09] menjaga batas itu supaya
// pengecualiannya tidak pelan-pelan melebar ke pengajuan awal.

use App\Actions\Monev\NotifyMonevEvaluationSubmitted;
use App\Actions\Monev\OpenMonevCycle;
use App\Actions\Review\AssignReviewer;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Notifications\MonevEvaluationSubmittedNotification;
use App\Notifications\MonevStageOpenedNotification;
use App\States\Submission\UnderReview;
use App\Support\ScoreVisibility;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('[MON-REVIEW-07] hasil evaluasi monev terlihat begitu reviewer submit', function () {
    Notification::fake();
    $fx = monevFixture(monev1NeedsReview: true);

    ['laporan' => $laporan] = monevReportWithEvaluation($fx, 'submitted');

    // Tidak menunggu siklus berakhir, tidak menunggu keputusan apa pun.
    expect(ScoreVisibility::visibleToResearcher($fx['owner'], $laporan->fresh()))->toBeTrue();
});

it('[MON-REVIEW-08] hasil evaluasi monev tertutup sebelum reviewer submit', function () {
    Notification::fake();
    $fx = monevFixture(monev1NeedsReview: true);

    ['laporan' => $laporan] = monevReportWithEvaluation($fx, 'draft');

    // Penilaian setengah jadi bukan umpan balik.
    expect(ScoreVisibility::visibleToResearcher($fx['owner'], $laporan->fresh()))->toBeFalse();
});

it('[MON-REVIEW-09] pengecualian monev tidak berlaku untuk pengajuan awal', function () {
    Notification::fake();
    $fx = monevFixture(parentStatus: UnderReview::class);

    // Pengajuan awal tetap tunduk BR-REV-12 — skor ditahan sampai keputusan
    // akhir, walau reviewernya sudah selesai menilai.
    expect(ScoreVisibility::visibleToResearcher($fx['owner'], $fx['parent']->fresh()))->toBeFalse();
});

it('[MON-REVIEW-10] pembukaan siklus memberi tahu peneliti', function () {
    Notification::fake();
    $fx = monevFixture();

    $diberitahu = OpenMonevCycle::run($fx['parent'], $fx['monev1']);

    expect($diberitahu)->toBe(1);
    Notification::assertSentTo($fx['owner'], MonevStageOpenedNotification::class);
});

it('[MON-REVIEW-11] pembukaan siklus memberi tahu reviewer yang ditugaskan', function () {
    Notification::fake();
    $fx = monevFixture(monev1NeedsReview: true);

    ['reviewer' => $reviewer] = monevReportWithEvaluation($fx, 'draft');

    $diberitahu = OpenMonevCycle::run($fx['parent'], $fx['monev1']);

    expect($diberitahu)->toBe(2);
    Notification::assertSentTo($reviewer->user, MonevStageOpenedNotification::class);
    Notification::assertSentTo($fx['owner'], MonevStageOpenedNotification::class);
});

it('[MON-REVIEW-12] submit evaluasi monev memberi tahu peneliti', function () {
    Notification::fake();
    $fx = monevFixture(monev1NeedsReview: true);

    ['response' => $response] = monevReportWithEvaluation($fx, 'submitted');

    expect(NotifyMonevEvaluationSubmitted::run($response))->toBeTrue();
    Notification::assertSentTo($fx['owner'], MonevEvaluationSubmittedNotification::class);
});

it('[MON-REVIEW-12] evaluasi pengajuan awal tidak memberi tahu peneliti', function () {
    Notification::fake();
    $fx = monevFixture();

    $reviewer = Reviewer::factory()->create([
        'user_id' => verifiedResearcher()->id,
        'start_date' => now()->subYear(),
        'end_date' => now()->addYear(),
    ]);
    $penugasan = AssignReviewer::run($fx['parent'], $reviewer);

    $form = ReviewEvaluationForm::create([
        'title' => 'Lembar Penilaian Pengajuan',
        'form_phase_detail_id' => $fx['pengajuan']->id,
        'is_required' => true,
        'is_active' => true,
        'order' => 1,
    ]);
    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $penugasan->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
    ]);
    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    // Skornya masih ditahan sampai keputusan akhir; mengabarkannya hanya
    // mengundang peneliti membuka halaman yang tetap kosong.
    expect(NotifyMonevEvaluationSubmitted::run($response))->toBeFalse();
    Notification::assertNotSentTo($fx['owner'], MonevEvaluationSubmittedNotification::class);
});

it('[MON-REVIEW-13] siklus yang belum dibuka tidak memberi tahu siapa pun', function () {
    Notification::fake();
    $fx = monevFixture();

    // Monev II belum boleh dibuka — Monev I belum tuntas.
    expect(fn () => OpenMonevCycle::run($fx['parent'], $fx['monev2']))
        ->toThrow(ValidationException::class);

    Notification::assertNothingSent();
});
