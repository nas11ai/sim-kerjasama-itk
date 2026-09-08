<?php

// tests/Feature/Reviewer/ReviewerDashboardTest.php
//
// Dasbor reviewer membaca `SubmissionReviewer` — tabel penugasan (BR-REV-01).
// Versi sebelumnya membacanya dari `ReviewSummary`, catatan diskusi yang baru
// lahir setelah reviewer mulai bekerja, sehingga penugasan yang belum
// disentuh tidak pernah muncul: pekerjaan yang paling perlu ditagih justru
// yang hilang dari layar.

use App\Actions\Review\BuildReviewerDashboard;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Submitted;

/** @return array{0: Reviewer, 1: User} */
function dashboardReviewer(): array
{
    $user = User::factory()->create();

    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    return [$reviewer, $user];
}

function assignTo(Reviewer $reviewer, string $evaluationStatus, string $status = 'active'): SubmissionReviewer
{
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'status' => Submitted::class,
    ]);

    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluationStatus,
        'status' => $status,
    ]);
}

it('menghitung penugasan yang belum dinilai sebagai tunggakan', function () {
    [$reviewer] = dashboardReviewer();

    assignTo($reviewer, 'pending');
    assignTo($reviewer, 'pending');
    assignTo($reviewer, 'completed');

    $dashboard = BuildReviewerDashboard::run($reviewer);

    expect($dashboard['stats'])->toMatchArray([
        'total_assigned' => 3,
        'pending_reviews' => 2,
        'completed_reviews' => 1,
    ]);
});

it('memperlakukan not_required sebagai selesai, sejajar dengan canProceed', function () {
    [$reviewer] = dashboardReviewer();

    assignTo($reviewer, 'not_required');

    expect(BuildReviewerDashboard::run($reviewer)['stats'])->toMatchArray([
        'pending_reviews' => 0,
        'completed_reviews' => 1,
    ]);
});

it('menampilkan penugasan yang belum punya ReviewSummary sama sekali', function () {
    [$reviewer] = dashboardReviewer();

    $assignment = assignTo($reviewer, 'pending');

    $dashboard = BuildReviewerDashboard::run($reviewer);

    expect($dashboard['assignments']->pluck('id')->all())->toBe([$assignment->id]);
});

it('mengabaikan penugasan yang sudah tidak aktif', function () {
    [$reviewer] = dashboardReviewer();

    assignTo($reviewer, 'pending');
    assignTo($reviewer, 'pending', status: 'inactive');

    expect(BuildReviewerDashboard::run($reviewer)['stats']['total_assigned'])->toBe(1);
});

it('tidak mencampur penugasan reviewer lain', function () {
    [$reviewer] = dashboardReviewer();
    [$lain] = dashboardReviewer();

    assignTo($reviewer, 'pending');
    assignTo($lain, 'pending');
    assignTo($lain, 'pending');

    expect(BuildReviewerDashboard::run($reviewer)['stats']['total_assigned'])->toBe(1);
});

it('membuka dasbor bagi user yang terdaftar sebagai reviewer', function () {
    [$reviewer, $user] = dashboardReviewer();

    assignTo($reviewer, 'pending');

    $this->actingAs($user)
        ->get(route('reviewer.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reviewer/DashboardPage')
                ->where('stats.total_assigned', 1)
                ->where('stats.pending_reviews', 1)
                ->has('assignments', 1)
        );
});

it('menolak user yang tidak terdaftar sebagai reviewer', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reviewer.dashboard'))
        ->assertForbidden();
});
