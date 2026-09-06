<?php

// tests/Feature/Review/ReviewerWorkloadTest.php
//
// BR-REV-04 — beban yang melebihi `max_reviewer_workload` memberi
// **peringatan, bukan penolakan**.
//
// Sengaja @boundary dan bukan @denial: menjadikannya penolakan keras
// melumpuhkan Operator di masa sibuk, dan itu keputusan produk — bukan bug.
//
// BR-REV-01 — reviewer yang ditugaskan menerima notifikasi.

use App\Actions\Review\AssignReviewer;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\ReviewerAssignedNotification;
use App\States\Submission\Submitted;
use App\Support\ReviewerWorkload;
use Illuminate\Support\Facades\Notification;

/** @return array{0: FormSubmission, 1: Reviewer} */
function workloadFixture(int $maxWorkload = 5, int $existing = 0): array
{
    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => 2, 'max_reviewer_workload' => $maxWorkload],
    ]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $scheme->id,
        'status' => Submitted::class,
    ]);

    $reviewer = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    for ($i = 0; $i < $existing; $i++) {
        $other = FormSubmission::factory()->create([
            'submitted_by' => User::factory()->create()->id,
            'scheme_id' => null,
            'status' => Submitted::class,
        ]);

        SubmissionReviewer::create([
            'form_submission_id' => $other->id,
            'reviewer_id' => $reviewer->id,
            'evaluation_status' => 'pending',
            'status' => 'active',
        ]);
    }

    return [$submission, $reviewer];
}

it('[REV-ASSIGN-06] beban di bawah batas tidak memberi peringatan', function () {
    [$submission, $reviewer] = workloadFixture(maxWorkload: 5, existing: 2);

    expect(ReviewerWorkload::warningFor($submission, $reviewer))->toBeNull();
});

it('[REV-ASSIGN-06] beban melebihi batas memberi peringatan', function () {
    [$submission, $reviewer] = workloadFixture(maxWorkload: 5, existing: 5);

    expect(ReviewerWorkload::warningFor($submission, $reviewer))->toContain('5');
});

it('[REV-ASSIGN-06] peringatan tidak menghalangi penugasan', function () {
    [$submission, $reviewer] = workloadFixture(maxWorkload: 1, existing: 3);

    // Inti aturannya: tetap boleh ditugaskan meski beban tinggi.
    $assignment = AssignReviewer::run($submission, $reviewer);

    expect($assignment->status)->toBe('active');
});

it('[REV-ASSIGN-06] skema tanpa max_reviewer_workload tidak pernah memperingatkan', function () {
    $scheme = Scheme::factory()->create(['rules' => ['min_reviewer_count' => 2]]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $scheme->id,
        'status' => Submitted::class,
    ]);
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    expect(ReviewerWorkload::warningFor($submission, $reviewer))->toBeNull();
});

it('[REV-ASSIGN-09] reviewer yang ditugaskan menerima notifikasi', function () {
    Notification::fake();

    [$submission, $reviewer] = workloadFixture();

    AssignReviewer::run($submission, $reviewer);

    Notification::assertSentTo($reviewer->user, ReviewerAssignedNotification::class);
});
