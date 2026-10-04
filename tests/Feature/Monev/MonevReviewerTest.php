<?php

// tests/Feature/Monev/MonevReviewerTest.php
//
// BR-MON-04/06/07 — reviewer monev ditugaskan manual per siklus.
//
// Tidak ada pewarisan otomatis dari siklus sebelumnya. Mewarisi akan membuat
// reviewer yang sudah tidak aktif diam-diam ikut terbawa, dan Operator tidak
// pernah diminta memutuskan — padahal keputusannya justru inti aturan ini.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\Monev\SuggestCycleReviewers;
use App\Actions\Review\AssignReviewer;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\States\Submission\Draft;
use Illuminate\Support\Facades\Notification;

function monevReviewer(string $endDate = '+1 year'): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => verifiedResearcher()->id,
        'reviewer_type' => 'internal',
        'start_date' => now()->subYear(),
        'end_date' => now()->modify($endDate),
    ]);
}

it('[MON-REVIEW-01] reviewer monev ditugaskan manual per siklus', function () {
    Notification::fake();
    $fx = monevFixture();

    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $reviewer = monevReviewer();

    $penugasan = AssignReviewer::run($laporan, $reviewer);

    expect($penugasan->form_submission_id)->toBe($laporan->id)
        ->and($penugasan->status)->toBe('active')
        // Laporan monev punya lifecycle-nya sendiri; ia tidak berpindah ke
        // UNDER_REVIEW seperti pengajuan.
        ->and($laporan->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[MON-REVIEW-02] tidak ada reviewer yang otomatis ikut dari siklus sebelumnya', function () {
    Notification::fake();
    $fx = monevFixture();

    $monev1 = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $reviewer = monevReviewer();
    AssignReviewer::run($monev1, $reviewer);

    $monev1->update(['is_submitted' => true]);
    $monev2 = StartChildSubmission::run($fx['parent'], $fx['monev2'], $fx['owner']);

    expect($monev2->submissionReviewers()->count())->toBe(0);
});

it('[MON-REVIEW-03] penugasan siklus membuat SubmissionReviewer baru', function () {
    Notification::fake();
    $fx = monevFixture();

    $monev1 = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $reviewer = monevReviewer();
    $pertama = AssignReviewer::run($monev1, $reviewer);

    $monev1->update(['is_submitted' => true]);
    $monev2 = StartChildSubmission::run($fx['parent'], $fx['monev2'], $fx['owner']);
    $kedua = AssignReviewer::run($monev2, $reviewer);

    expect($kedua->id)->not->toBe($pertama->id)
        ->and(SubmissionReviewer::where('reviewer_id', $reviewer->id)->count())->toBe(2);
});

it('[MON-REVIEW-04] evaluasi siklus sebelumnya tetap terbaca sebagai riwayat', function () {
    Notification::fake();
    $fx = monevFixture();

    $monev1 = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $reviewer = monevReviewer();
    $pertama = AssignReviewer::run($monev1, $reviewer);
    $pertama->update(['evaluation_status' => 'completed']);

    $monev1->update(['is_submitted' => true]);
    $monev2 = StartChildSubmission::run($fx['parent'], $fx['monev2'], $fx['owner']);
    AssignReviewer::run($monev2, $reviewer);

    // Record siklus lama tidak dipakai ulang, jadi penilaiannya tidak tertimpa.
    expect($pertama->fresh()->evaluation_status)->toBe('completed')
        ->and($pertama->fresh()->form_submission_id)->toBe($monev1->id);
});

it('[MON-REVIEW-05] reviewer yang masa tugasnya lewat tidak muncul di saran', function () {
    Notification::fake();
    $fx = monevFixture();

    // Ditugaskan saat masih aktif, lalu masa tugasnya berakhir — itulah kasus
    // nyata yang BR-MON-07 jaga. Reviewer yang sudah habis saat penugasan
    // awalnya tidak akan pernah ada di daftar itu sejak semula.
    $habis = monevReviewer();
    AssignReviewer::run($fx['parent'], $habis);

    $habis->update(['end_date' => now()->subMonth()]);

    $saran = SuggestCycleReviewers::run($fx['parent']->fresh());

    expect($saran->pluck('id')->all())->not->toContain($habis->id);
});

it('[MON-REVIEW-06] reviewer awal yang masih aktif muncul sebagai saran', function () {
    Notification::fake();
    $fx = monevFixture();

    $aktif = monevReviewer();
    AssignReviewer::run($fx['parent'], $aktif);

    $saran = SuggestCycleReviewers::run($fx['parent']->fresh());

    expect($saran->pluck('id')->all())->toContain($aktif->id);
});

it('[MON-REVIEW-06] Operator tetap bisa memilih reviewer di luar daftar saran', function () {
    Notification::fake();
    $fx = monevFixture();

    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    // Belum pernah menjadi reviewer pengajuan ini, jadi tidak ada di saran —
    // dan itu tidak menghalangi penugasannya. Saran adalah kemudahan, bukan
    // penyaring; mencampurnya akan mengubahnya jadi larangan.
    $orangBaru = monevReviewer();

    expect(SuggestCycleReviewers::run($fx['parent'])->pluck('id')->all())->not->toContain($orangBaru->id);

    $penugasan = AssignReviewer::run($laporan, $orangBaru);

    expect($penugasan->reviewer_id)->toBe($orangBaru->id);
});

it('BR-MON-07 dan BR-REV-09 — reviewer yang end_date-nya hari ini masih disarankan', function () {
    Notification::fake();
    $this->travelTo(today()->setTime(12, 0));
    $fx = monevFixture();

    $hariTerakhir = monevReviewer();
    AssignReviewer::run($fx['parent'], $hariTerakhir);
    $hariTerakhir->update(['end_date' => today()->toDateString()]);

    $saran = SuggestCycleReviewers::run($fx['parent']->fresh());

    expect($saran->pluck('id')->all())->toContain($hariTerakhir->id);
});
