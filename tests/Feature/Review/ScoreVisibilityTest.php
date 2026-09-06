<?php

// tests/Feature/Review/ScoreVisibilityTest.php
//
// BR-REV-11 — reviewer_internal melihat skor rekannya setelah SELURUH
// evaluasi selesai; reviewer_external tidak pernah.
//
// BR-REV-12 — researcher baru melihat skor setelah keputusan final
// (APPROVED atau REJECTED), tidak selama proses berlangsung.
//
// Dua aturan yang sering tertukar padahal sumbunya berbeda: yang satu
// tentang WAKTU (semua selesai atau belum), yang satu tentang SIAPA (tipe
// reviewer). Menyatukannya menghapus perbedaan yang justru jadi intinya.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use App\Support\ScoreVisibility;

/** @return array{0: User, 1: FormSubmission} */
function visibilityFixture(string $state = UnderReview::class): array
{
    $owner = User::factory()->create();
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => $state,
    ]);

    return [$owner, $submission];
}

function reviewerOfType(FormSubmission $submission, string $type, string $evaluation = 'completed'): User
{
    $user = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $user->id, 'reviewer_type' => $type]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => 'active',
    ]);

    return $user;
}

it('[REV-VIS-01] reviewer internal melihat skor rekannya setelah semua selesai', function () {
    [, $submission] = visibilityFixture();
    $internal = reviewerOfType($submission, 'internal');
    reviewerOfType($submission, 'internal');

    expect(ScoreVisibility::peerScoresVisibleTo($internal, $submission))->toBeTrue();
});

it('[REV-VIS-02] reviewer eksternal tidak pernah melihat skor rekannya', function () {
    [, $submission] = visibilityFixture();
    $external = reviewerOfType($submission, 'external');
    reviewerOfType($submission, 'internal');

    // Bukan soal waktu: seluruh evaluasi sudah selesai, dan tetap tertutup.
    expect(ScoreVisibility::peerScoresVisibleTo($external, $submission))->toBeFalse();
});

it('[REV-VIS-03] skor rekan tertutup selama masih ada yang belum selesai', function () {
    [, $submission] = visibilityFixture();
    $internal = reviewerOfType($submission, 'internal');
    reviewerOfType($submission, 'internal', 'pending');

    // Sumbu waktu — supaya penilaian tidak saling memengaruhi.
    expect(ScoreVisibility::peerScoresVisibleTo($internal, $submission))->toBeFalse();
});

it('[REV-VIS-03] reviewer not_required ikut dihitung selesai', function () {
    [, $submission] = visibilityFixture();
    $internal = reviewerOfType($submission, 'internal');
    reviewerOfType($submission, 'internal', 'not_required');

    expect(ScoreVisibility::peerScoresVisibleTo($internal, $submission))->toBeTrue();
});

it('[REV-VIS-04] researcher tidak melihat skor selama UNDER_REVIEW', function () {
    [$owner, $submission] = visibilityFixture();
    reviewerOfType($submission, 'internal');

    expect(ScoreVisibility::visibleToResearcher($owner, $submission))->toBeFalse();
});

it('[REV-VIS-05] researcher melihat skor setelah APPROVED', function () {
    [$owner, $submission] = visibilityFixture(Approved::class);

    expect(ScoreVisibility::visibleToResearcher($owner, $submission))->toBeTrue();
});

it('[REV-VIS-06] researcher melihat skor setelah REJECTED', function () {
    [$owner, $submission] = visibilityFixture(Rejected::class);

    // Ditolak pun berhak tahu alasannya — justru di situ skornya paling
    // dibutuhkan untuk perbaikan.
    expect(ScoreVisibility::visibleToResearcher($owner, $submission))->toBeTrue();
});

it('[REV-VIS-04] orang lain tetap tidak melihat skor meski sudah APPROVED', function () {
    [, $submission] = visibilityFixture(Approved::class);

    $outsider = User::factory()->create();

    expect(ScoreVisibility::visibleToResearcher($outsider, $submission))->toBeFalse();
});
