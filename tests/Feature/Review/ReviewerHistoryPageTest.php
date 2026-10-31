<?php

// tests/Feature/Review/ReviewerHistoryPageTest.php
//
// REVIEWER-03-02 — riwayat evaluasi reviewer: satu baris per SubmissionReviewer, semua status.
// Record lama tidak pernah dipakai ulang atau dihapus (BR-REV-10, BR-MON-07).

use App\Actions\Review\ReplaceReviewer;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->reviewer = historyPageReviewer($this->user);
});

function historyPageReviewer(?User $user = null): Reviewer
{
    $user ??= User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));

    return Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

function historyPageAssignment(Reviewer $reviewer, string $title, string $evaluation = 'completed'): SubmissionReviewer
{
    $submission = FormSubmission::factory()->create([
        'form_id' => Form::factory()->create(['title' => $title])->id,
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'status' => UnderReview::class,
    ]);

    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => 'active',
    ]);
}

it('reviewer membuka Review/HistoryPage dengan penugasannya', function () {
    $assignment = historyPageAssignment($this->reviewer, 'Smart Farming Hidroponik');

    $this->actingAs($this->user)
        ->get(route('reviewer.history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/HistoryPage')
            ->has('history.data', 1)
            ->where('history.data.0.title', 'Smart Farming Hidroponik')
            ->where('history.data.0.status', 'completed')
            ->where('history.data.0.statusLabel', 'Selesai')
            ->where('history.data.0.summaryUrl', route('reviewer.assignments.summary', $assignment->form_submission_id))
            ->where('filters', ['search' => '', 'year' => '']));
});

it('[REV-REASSIGN-02] penugasan yang digantikan tetap terdaftar sebagai riwayat', function () {
    $assignment = historyPageAssignment($this->reviewer, 'Katalis Limbah Sawit');
    ReplaceReviewer::run($assignment->formSubmission, $this->reviewer, historyPageReviewer());

    $this->actingAs($this->user)
        ->get(route('reviewer.history.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)
            ->where('history.data.0.status', 'replaced')
            ->where('history.data.0.statusLabel', 'Digantikan')
            // BR-REV-10 — penugasan lama tidak lagi membuka layar ringkasan.
            ->where('history.data.0.summaryUrl', null)
            ->etc());
});

it('[MON-REVIEW-04] siklus monev berbeda tampil sebagai dua baris', function () {
    $fx = monevFixture();
    foreach ([$fx['monev1'], $fx['monev2']] as $cycle) {
        $child = FormSubmission::factory()->create([
            'submitted_by' => $fx['owner']->id,
            'parent_submission_id' => $fx['parent']->id,
            'form_phase_detail_id' => $cycle->id,
        ]);
        SubmissionReviewer::create([
            'form_submission_id' => $child->id,
            'reviewer_id' => $this->reviewer->id,
            'evaluation_status' => 'pending',
            'status' => 'active',
        ]);
    }

    $this->actingAs($this->user)
        ->get(route('reviewer.history.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 2)
            ->where('history.data.0.status', 'pending')
            ->where('history.data.0.statusLabel', 'Belum selesai')
            ->etc());
});

it('riwayat reviewer lain tidak terdaftar', function () {
    historyPageAssignment(historyPageReviewer(), 'Milik Reviewer Lain');

    $this->actingAs($this->user)
        ->get(route('reviewer.history.index'))
        ->assertInertia(fn (Assert $page) => $page->has('history.data', 0)->etc());
});

it('pencarian judul tidak peka huruf besar', function () {
    historyPageAssignment($this->reviewer, 'Pemetaan Karbon Biru');
    historyPageAssignment($this->reviewer, 'Smart Farming Hidroponik');

    $this->actingAs($this->user)
        ->get(route('reviewer.history.index', ['search' => 'karbon']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)
            ->where('history.data.0.title', 'Pemetaan Karbon Biru')
            ->where('filters.search', 'karbon')
            ->etc());
});

it('saringan tahun memakai tahun penugasan', function () {
    historyPageAssignment($this->reviewer, 'Tahun Lalu')->forceFill(['created_at' => now()->subYear()])->save();
    historyPageAssignment($this->reviewer, 'Tahun Ini');

    $this->actingAs($this->user)
        ->get(route('reviewer.history.index', ['year' => now()->format('Y')]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)
            ->where('history.data.0.title', 'Tahun Ini')
            ->has('yearOptions', 2)
            ->etc());
});

it('pengguna tanpa reviewers.evaluate ditolak', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reviewer.history.index'))
        ->assertForbidden();
});
