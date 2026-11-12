<?php

// tests/Feature/Dashboard/ReviewerDashboardCardsTest.php
//
// SHARED-02-02 — kartu "Review". Dibaca dari penugasan (`SubmissionReviewer`,
// BR-REV-01) lewat ReviewerAssignmentCards, bukan dari `ReviewSummary`:
// penugasan yang belum disentuh justru pekerjaan yang paling perlu ditagih.
// Dihitung per state, bukan per keranjang — keranjang "perlu tindakan" juga
// memuat diskusi terbuka yang evaluasinya sudah selesai (BR-MON-02).

use App\Data\View\Dashboard\DeadlineData;
use App\Data\View\Dashboard\KpiCardData;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Dashboard\ReviewerDashboardCards;
use App\States\Submission\Submitted;

function rvReviewer(): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

function rvAssign(Reviewer $reviewer, string $evaluationStatus, string $status = 'active', ?string $due = null): SubmissionReviewer
{
    $assignment = SubmissionReviewer::create([
        'form_submission_id' => FormSubmission::factory()->create(['status' => Submitted::class])->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluationStatus,
        'status' => $status,
    ]);

    if ($due !== null) {
        ReviewerFormAssignment::create([
            'submission_reviewer_id' => $assignment->id,
            'review_evaluation_form_id' => evaluationFormOn(FormPhaseDetail::factory()->create(['needs_review' => true]), [], title: 'Form '.fake()->unique()->numerify('####'))->id,
            'is_required' => true,
            'assigned_at' => now(),
            'due_date' => $due,
            'is_active' => true,
        ]);
    }

    return $assignment;
}

/** @return array<string, KpiCardData> */
function rvCards(Reviewer $reviewer): array
{
    return collect(ReviewerDashboardCards::run($reviewer)['section']->cards)->keyBy('key')->all();
}

it('section "Review" berisi tiga kartu frame', function () {
    $section = ReviewerDashboardCards::run(rvReviewer())['section'];

    expect($section->key)->toBe('reviewer')
        ->and(array_map(fn (KpiCardData $c) => $c->label, $section->cards))
        ->toBe(['Perlu Dievaluasi', 'Evaluasi Selesai', 'Deadline Terdekat']);
});

it('penugasan yang belum dinilai adalah tunggakan', function () {
    $reviewer = rvReviewer();
    rvAssign($reviewer, 'pending');
    rvAssign($reviewer, 'pending');
    rvAssign($reviewer, 'completed');

    $cards = rvCards($reviewer);

    expect($cards['pending']->value)->toBe('2')
        ->and($cards['done']->value)->toBe('1')
        ->and($cards['done']->meta)->toBe('Total');
});

it('not_required terhitung selesai, sejajar canProceed', function () {
    $reviewer = rvReviewer();
    rvAssign($reviewer, 'not_required');

    $cards = rvCards($reviewer);

    expect($cards['pending']->value)->toBe('0')
        ->and($cards['done']->value)->toBe('1');
});

it('diskusi terbuka atas evaluasi selesai tidak dihitung perlu dievaluasi (BR-MON-02)', function () {
    $reviewer = rvReviewer();
    $assignment = rvAssign($reviewer, 'completed');
    ReviewSummary::create([
        'form_submission_id' => $assignment->form_submission_id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Mohon perbaiki',
    ]);

    $cards = rvCards($reviewer);

    expect($cards['pending']->value)->toBe('0')
        ->and($cards['done']->value)->toBe('1');
});

it('penugasan nonaktif diabaikan dan reviewer lain tidak tercampur', function () {
    $reviewer = rvReviewer();
    rvAssign($reviewer, 'pending');
    rvAssign($reviewer, 'pending', status: 'replaced');
    rvAssign(rvReviewer(), 'pending');

    expect(rvCards($reviewer)['pending']->value)->toBe('1');
});

it('reviewer tanpa penugasan', function () {
    $result = ReviewerDashboardCards::run(rvReviewer());
    $cards = collect($result['section']->cards)->keyBy('key');

    expect($cards['pending']->value)->toBe('0')
        ->and($cards['done']->value)->toBe('0')
        ->and($cards['nearest']->value)->toBe('—')
        ->and($cards['nearest']->meta)->toBeNull()
        ->and($result['deadlines'])->toBe([]);
});

it('mendekati deadline memakai URGENT_DAYS', function () {
    $this->freezeTime();
    $reviewer = rvReviewer();
    $dekat = rvAssign($reviewer, 'pending', due: now()->addDays(DeadlineData::URGENT_DAYS)->toDateTimeString());
    rvAssign($reviewer, 'pending', due: now()->addDays(DeadlineData::URGENT_DAYS + 1)->toDateTimeString());

    $result = ReviewerDashboardCards::run($reviewer);
    $cards = collect($result['section']->cards)->keyBy('key');

    expect($cards['pending']->meta)->toBe('1 mendekati deadline')
        ->and($cards['nearest']->value)->toBe(DeadlineData::URGENT_DAYS.' hari')
        ->and($result['deadlines'])->toHaveCount(2)
        ->and($result['deadlines'][0]->urgent)->toBeTrue()
        ->and($result['deadlines'][0]->href)->toBe(route('reviewer.assignments.evaluation', $dekat->form_submission_id))
        ->and($result['deadlines'][1]->urgent)->toBeFalse();
});

it('tenggat hari ini ditulis "Hari ini"', function () {
    $this->freezeTime();
    $reviewer = rvReviewer();
    rvAssign($reviewer, 'pending', due: now()->endOfDay()->toDateTimeString());

    expect(rvCards($reviewer)['nearest']->value)->toBe('Hari ini');
});

it('penugasan selesai tidak muncul di daftar deadline', function () {
    $reviewer = rvReviewer();
    rvAssign($reviewer, 'completed', due: now()->addDays(2)->toDateTimeString());

    expect(ReviewerDashboardCards::run($reviewer)['deadlines'])->toBe([]);
});
