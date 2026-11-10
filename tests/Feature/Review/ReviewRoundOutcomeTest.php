<?php

// tests/Feature/Review/ReviewRoundOutcomeTest.php
//
// BR-REV-16 — hasil review dihitung sekali, saat peserta terakhir putaran menekan Selesai.
// BR-REV-06 — catatan `open` saat itu → NEEDS_REVISION serentak; tidak ada → siap diputuskan
// dan Operator diberi tahu. BR-REV-14 — tidak ada kombinasi yang menghasilkan REJECTED, dan
// sejak ADR 0018 juga tidak APPROVED. BR-REV-17 — catatan hanya dibuka peserta putaran berjalan
// yang belum Selesai. Fixture `outcomeSubmission`, `reviewerOn`, `finishSeat` di fixtures.php.

use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Actions\Review\AssignReviewers;
use App\Actions\Review\ConcludeReviewRound;
use App\Actions\Review\FinishReviewRound;
use App\Actions\Review\OpenReviewThread;
use App\Actions\Review\ResolveReviewSummary;
use App\Models\FieldType;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormField;
use App\Models\ReviewRound;
use App\Models\ReviewRoundParticipant;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\RevisionRequestedNotification;
use App\Notifications\SubmissionReadyForDecisionNotification;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
});

function decisionOperator(): User
{
    $operator = User::factory()->create();
    $operator->givePermissionTo(Permission::findOrCreate('submissions.view-all', 'web'));

    return $operator;
}

function openNoteBy(FormSubmission $submission, SubmissionReviewer $seat): ReviewSummary
{
    return OpenReviewThread::run($submission, $seat->refresh(), 'Perkuat metodologi.');
}

function markFinished(SubmissionReviewer $seat): void
{
    ReviewRoundParticipant::query()->where('submission_reviewer_id', $seat->id)->update(['finished_at' => now()]);
}

/**
 * Pengajuan NEEDS_REVISION setelah putaran penilaian; A dan B sama-sama punya catatan terbuka,
 * lalu peneliti mengirim ulang sehingga putaran pemeriksaan berjalan.
 *
 * @return array{0: FormSubmission, 1: SubmissionReviewer, 2: SubmissionReviewer, 3: ReviewSummary, 4: ReviewSummary}
 */
function recheckFixture(): array
{
    $submission = outcomeSubmission(NeedsRevision::class);
    $a = reviewerOn($submission, 'completed');
    $b = reviewerOn($submission, 'completed');
    $noteA = openSummaryOn($submission, $a);
    $noteB = openSummaryOn($submission, $b);
    $submission->latestReviewRound()->update(['completed_at' => now(), 'outcome' => ReviewRound::OUTCOME_NEEDS_REVISION]);

    ResubmitFormSubmission::run($submission, $submission->submittedBy);

    return [$submission->refresh(), $a, $b, $noteA, $noteB];
}

function submitLastEvaluation(User $reviewer, ReviewerFormAssignment $assignment): void
{
    $field = ReviewFormField::create([
        'review_evaluation_form_id' => $assignment->review_evaluation_form_id,
        'label' => 'Kelayakan',
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'is_required' => false,
        'order' => 1,
    ]);

    test()->actingAs($reviewer)
        ->post(route('reviewer.evaluation-form.submit', $assignment), ['responses' => [$field->id => 'Layak']])
        ->assertSessionHasNoErrors();
}

it('[REV-OUTCOME-01] semua peserta Selesai menilai tanpa catatan terbuka membuat pengajuan siap diputuskan', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    $b = reviewerOn($submission, 'completed');

    finishSeat($a);
    finishSeat($b);

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and($submission->latestReviewRound()->isRunning())->toBeFalse()
        ->and($submission->latestReviewRound()->outcome)->toBe(ReviewRound::OUTCOME_READY);
});

it('[REV-OUTCOME-02] satu peserta belum Selesai menilai menahan putaran', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');

    finishSeat($a);

    expect($submission->latestReviewRound()->isRunning())->toBeTrue()
        ->and($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[REV-OUTCOME-04] peserta terakhir Selesai dengan catatan terbuka menjadikan NEEDS_REVISION', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    $b = reviewerOn($submission, 'completed');
    openNoteBy($submission, $a);

    finishSeat($a);
    finishSeat($b);

    expect($submission->fresh()->status)->toBeInstanceOf(NeedsRevision::class)
        ->and($submission->latestReviewRound()->outcome)->toBe(ReviewRound::OUTCOME_NEEDS_REVISION);
    Notification::assertSentTo($submission->submittedBy, RevisionRequestedNotification::class);
});

it('[REV-OUTCOME-06] peserta not_required dihitung selesai', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'not_required');

    finishSeat($a);

    expect($submission->latestReviewRound()->isRunning())->toBeFalse()
        ->and($submission->latestReviewRound()->outcome)->toBe(ReviewRound::OUTCOME_READY);
});

it('[REV-OUTCOME-08] tidak ada kombinasi yang menghasilkan REJECTED atau APPROVED', function (bool $withNote, bool $withNotRequired) {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    $b = reviewerOn($submission, $withNotRequired ? 'not_required' : 'completed');

    if ($withNote) {
        openNoteBy($submission, $a);
    }

    finishSeat($a);
    if (!$withNotRequired) {
        finishSeat($b);
    }

    // Skor serendah apa pun tidak menolak, dan putaran yang selesai tidak menyetujui:
    // keduanya keputusan Operator (BR-REV-07, BR-REV-14, BR-REV-05).
    expect($submission->latestReviewRound()->isRunning())->toBeFalse()
        ->and($submission->fresh()->status)->not->toBeInstanceOf(Rejected::class)
        ->and($submission->fresh()->status)->not->toBeInstanceOf(Approved::class);
})->with([
    'catatan, tanpa not_required' => [true, false],
    'catatan, dengan not_required' => [true, true],
    'tanpa catatan, tanpa not_required' => [false, false],
    'tanpa catatan, dengan not_required' => [false, true],
]);

it('[REV-OUTCOME-13] reviewer terakhir masih bisa meminta revisi setelah mengirim lembarnya', function () {
    $fx = reviewedDetailFixture('+1 month', '+2 months');
    $submission = $fx['submission'];
    $a = reviewerOn($submission, 'completed');
    finishSeat($a);

    submitLastEvaluation($fx['reviewer'], $fx['assignment']);

    // Mengirim lembar tidak memicu hasil (BR-REV-16) — inilah celah 2 yang ditutup.
    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and($submission->latestReviewRound()->isRunning())->toBeTrue();

    $b = $fx['assignment']->submissionReviewer;
    openNoteBy($submission, $b);
    finishSeat($b);

    expect($submission->fresh()->status)->toBeInstanceOf(NeedsRevision::class);
});

it('[REV-OUTCOME-14] Selesai menilai ditolak selama evaluasi belum completed', function () {
    $submission = outcomeSubmission();
    $b = reviewerOn($submission, 'pending');

    expect(fn () => finishSeat($b))->toThrow(ValidationException::class);

    try {
        finishSeat($b);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('round')
            ->and($e->errors()['round'][0])->toBe('Kirim lembar penilaian lebih dulu.');
    }
});

it('Selesai kedua kalinya ditolak dengan pesan tetap', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');
    finishSeat($a);

    try {
        finishSeat($a);
        $this->fail('Selesai kedua kalinya seharusnya ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors()['round'][0])->toBe('Anda sudah menyelesaikan putaran ini.');
    }
});

it('Selesai atas kursi milik orang lain ditolak', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');

    expect(fn () => FinishReviewRound::run($a, User::factory()->create()))
        ->toThrow(AuthorizationException::class);
});

it('[REV-OUTCOME-15] setelah Selesai, membuka catatan baru di putaran itu ditolak', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');
    finishSeat($a);

    try {
        openNoteBy($submission, $a);
        $this->fail('Catatan setelah Selesai seharusnya ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('review_summary');
    }

    expect($submission->reviewSummaries()->count())->toBe(0);
});

it('[REV-OUTCOME-17] menutup satu catatan saat catatan rekan terbuka tidak mengembalikan ke revisi', function () {
    [$submission, $a, , $noteA] = recheckFixture();

    ResolveReviewSummary::run($noteA, $a->reviewer->user);

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and($submission->runningReviewRound())->not->toBeNull();
});

it('[REV-OUTCOME-18] putaran pemeriksaan selesai dengan catatan terbuka menjadikan NEEDS_REVISION serentak', function () {
    [$submission, $a, $b, $noteA] = recheckFixture();
    ResolveReviewSummary::run($noteA, $a->reviewer->user);

    finishSeat($a);
    finishSeat($b);

    expect($submission->fresh()->status)->toBeInstanceOf(NeedsRevision::class);
    Notification::assertSentToTimes($submission->submittedBy, RevisionRequestedNotification::class, 1);
});

it('[REV-OUTCOME-05] menutup catatan terakhir tidak mengubah status; putaran pemeriksaan tanpa catatan membuat siap', function () {
    [$submission, $a, $b, $noteA, $noteB] = recheckFixture();

    ResolveReviewSummary::run($noteA, $a->reviewer->user);
    ResolveReviewSummary::run($noteB, $b->reviewer->user);

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);

    finishSeat($a);
    finishSeat($b);

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class)
        ->and($submission->latestReviewRound()->outcome)->toBe(ReviewRound::OUTCOME_READY);
});

it('[REV-OUTCOME-20] Operator diberi tahu saat putaran selesai tanpa catatan terbuka', function () {
    $operator = decisionOperator();
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');

    finishSeat($a);

    Notification::assertSentTo($operator, SubmissionReadyForDecisionNotification::class);
});

it('[REV-OUTCOME-20] Operator tidak diberi tahu saat hasilnya NEEDS_REVISION', function () {
    $operator = decisionOperator();
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    openNoteBy($submission, $a);

    finishSeat($a);

    Notification::assertNotSentTo($operator, SubmissionReadyForDecisionNotification::class);
});

it('kesimpulan putaran terjadi sekali walau dipanggil dua kali', function () {
    $operator = decisionOperator();
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    markFinished($a);
    $round = $submission->runningReviewRound();

    expect(ConcludeReviewRound::run($round))->toBeTrue()
        ->and(ConcludeReviewRound::run($round))->toBeFalse();
    Notification::assertSentToTimes($operator, SubmissionReadyForDecisionNotification::class, 1);
});

it('pemeriksaan tanpa peserta langsung siap', function () {
    $operator = decisionOperator();
    $submission = outcomeSubmission(NeedsRevision::class);
    $a = reviewerOn($submission, 'completed');
    $note = openSummaryOn($submission, $a);
    $submission->latestReviewRound()->update(['completed_at' => now(), 'outcome' => ReviewRound::OUTCOME_NEEDS_REVISION]);
    // Reviewer menutup catatannya selama NEEDS_REVISION, sebelum peneliti mengirim ulang.
    ResolveReviewSummary::run($note, $a->reviewer->user);

    ResubmitFormSubmission::run($submission, $submission->submittedBy);

    expect($submission->latestReviewRound()->participants()->count())->toBe(0)
        ->and($submission->latestReviewRound()->outcome)->toBe(ReviewRound::OUTCOME_READY);
    Notification::assertSentTo($operator, SubmissionReadyForDecisionNotification::class);
});

it('reviewer not_required di tengah putaran menyimpulkan putaran', function () {
    $submission = outcomeSubmission(Submitted::class);
    $a = reviewerOn($submission, 'completed');
    markFinished($a);
    $incoming = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    // Fase tanpa form evaluasi → kursi baru `not_required` (BR-FE-06).
    AssignReviewers::run($submission, [$incoming->id], false);

    expect($submission->latestReviewRound()->isRunning())->toBeFalse()
        ->and($submission->latestReviewRound()->outcome)->toBe(ReviewRound::OUTCOME_READY);
});

it('Selesai yang kalah balapan dengan permintaan kembarnya ditolak tanpa audit kedua', function () {
    $submission = outcomeSubmission();
    $a = reviewerOn($submission, 'completed');
    reviewerOn($submission, 'completed');

    // Permintaan kembar menulis tanda Selesai tepat setelah baris peserta dibaca permintaan ini.
    ReviewRoundParticipant::retrieved(function (ReviewRoundParticipant $participant) use ($a): void {
        if ($participant->submission_reviewer_id === $a->id) {
            Illuminate\Support\Facades\DB::table('review_round_participants')
                ->where('id', $participant->id)->update(['finished_at' => now()]);
        }
    });

    try {
        finishSeat($a);
        $this->fail('Tanda Selesai kedua seharusnya ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors()['round'][0])->toBe('Anda sudah menyelesaikan putaran ini.');
    }

    expect(Spatie\Activitylog\Models\Activity::query()
        ->where('description', 'Putaran review diselesaikan reviewer')->count())->toBe(0);
});
