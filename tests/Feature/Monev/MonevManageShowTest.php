<?php

// tests/Feature/Monev/MonevManageShowTest.php
//
// OPERATOR-05-02 — aktivasi siklus (BR-MON-10, W126) dan penugasan reviewer per siklus
// (BR-MON-04/06/07), lewat HTTP. Wewenang W129.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\Review\AssignReviewer;
use App\Enums\MonevCycleState;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\MonevStageOpenedNotification;
use App\Queries\Monev\MonevCycles;
use App\States\Submission\UnderReview;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Notification::fake();
});

function monevManager(): User
{
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    return $operator;
}

function monevCycleReviewer(string $endDate = '+1 year'): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => verifiedResearcher()->id,
        'reviewer_type' => 'internal',
        'start_date' => now()->subYear(),
        'end_date' => now()->modify($endDate),
    ]);
}

function monevReportOf(array $fx, string $cycle): ?FormSubmission
{
    return FormSubmission::query()
        ->where('parent_submission_id', $fx['parent']->id)
        ->where('form_phase_detail_id', $fx[$cycle]->id)
        ->first();
}

function openCycle(User $operator, array $fx, string $cycle): TestResponse
{
    return test()->actingAs($operator)
        ->from(route('monev.manage.show', $fx['parent']))
        ->post(route('monev.manage.open', ['submission' => $fx['parent'], 'detail' => $fx[$cycle]]));
}

function assignCycle(User $operator, array $fx, string $cycle, Reviewer $reviewer): TestResponse
{
    return test()->actingAs($operator)
        ->from(route('monev.manage.show', $fx['parent']))
        ->post(
            route('monev.manage.reviewers.store', ['submission' => $fx['parent'], 'detail' => $fx[$cycle]]),
            ['reviewer_id' => $reviewer->id],
        );
}

it('kontrak aktivasi siklus', function () {
    $fx = monevFixture();

    $this->actingAs(monevManager())->get(route('monev.manage.show', $fx['parent']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Monev/ManageShowPage')
            ->where('submission.id', $fx['parent']->id)
            ->where('submission.title', $fx['parent']->form->title)
            ->where('submission.leader', $fx['owner']->name)
            ->where('submission.status.key', 'approved')
            ->where('suggestedReviewerIds', [])
            ->missing('reviewerCandidates')
            ->has('cycles', 2)
            ->has('cycles.0', fn (AssertableInertia $cycle) => $cycle
                ->where('cycle.name', 'Monev I')
                ->where('cycle.state', 'open')
                ->where('cycle.openedAt', null)
                ->where('reviewers', [])
                ->where('phaseAccess', null)
                ->where('overrideTarget', null)
                ->where('canOpen', true)
                ->where('openHint', null)
                ->where('canAssign', true)
                ->where('canExtend', false))
            ->where('cycles.1.canOpen', false)
            ->where('cycles.1.openHint', 'Aktivasi terbuka setelah siklus Monev I selesai dievaluasi.')
            ->where('cycles.1.canAssign', false));
});

it('wewenang: operator 200, peneliti 403; tugaskan tanpa reviewers.assign 403', function () {
    $fx = monevFixture();
    $fx['owner']->assignRole('researcher');

    $this->actingAs(monevManager())->get(route('monev.manage.show', $fx['parent']))->assertOk();
    $this->actingAs($fx['owner'])->get(route('monev.manage.show', $fx['parent']))->assertForbidden();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findByName('submissions.view-all', 'web'));

    assignCycle($viewer, $fx, 'monev1', monevCycleReviewer())->assertForbidden();
    expect(monevReportOf($fx, 'monev1'))->toBeNull();
});

it('hanya induk APPROVED atau WITHDRAWN yang dikelola', function () {
    $review = monevFixture(parentStatus: UnderReview::class);
    $fx = monevFixture();
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    $this->actingAs(monevManager())->get(route('monev.manage.show', $review['parent']))->assertNotFound();
    $this->actingAs(monevManager())->get(route('monev.manage.show', $laporan))->assertNotFound();
});

it('[MON-REVIEW-10] mengaktifkan siklus membuat laporan milik ketua dan memberi tahu ketua', function () {
    $fx = monevFixture();

    openCycle(monevManager(), $fx, 'monev1')
        ->assertRedirect(route('monev.manage.show', $fx['parent']))
        ->assertSessionHas('success', 'Siklus dibuka. 1 orang diberi tahu.');

    $laporan = monevReportOf($fx, 'monev1');
    expect($laporan)->not->toBeNull()
        ->and($laporan->submitted_by)->toBe($fx['owner']->id)
        ->and($laporan->cycle_opened_at)->not->toBeNull()
        ->and(MonevCycles::run($fx['parent'])[0]->openedAt)->not->toBeNull();
    Notification::assertSentTo($fx['owner'], MonevStageOpenedNotification::class);
});

it('[MON-REVIEW-14] siklus yang sudah diaktifkan tidak diaktifkan dua kali', function () {
    $fx = monevFixture();
    $operator = monevManager();
    openCycle($operator, $fx, 'monev1');

    openCycle($operator, $fx, 'monev1')
        ->assertSessionHasErrors(['form_phase_detail_id' => 'Siklus ini sudah diaktifkan pada '.now()->translatedFormat('d F Y').'.']);

    Notification::assertSentToTimes($fx['owner'], MonevStageOpenedNotification::class, 1);
    expect(FormSubmission::query()->where('parent_submission_id', $fx['parent']->id)->count())->toBe(1);
});

it('[MON-REVIEW-13] siklus terkunci tidak bisa diaktifkan', function () {
    $fx = monevFixture();

    openCycle(monevManager(), $fx, 'monev2')->assertSessionHasErrors('form_phase_detail_id');

    Notification::assertNothingSent();
    expect(monevReportOf($fx, 'monev2'))->toBeNull();
});

it('[MON-REVIEW-01] menugaskan reviewer siklus lewat HTTP', function () {
    $fx = monevFixture();
    $reviewer = monevCycleReviewer();

    assignCycle(monevManager(), $fx, 'monev1', $reviewer)
        ->assertRedirect(route('monev.manage.show', $fx['parent']))
        ->assertSessionHas('success', 'Reviewer ditugaskan.');

    $laporan = monevReportOf($fx, 'monev1');
    expect($laporan->submitted_by)->toBe($fx['owner']->id)
        ->and($laporan->submissionReviewers()->where('reviewer_id', $reviewer->id)->where('status', 'active')->exists())->toBeTrue();
});

it('[MON-REVIEW-02] siklus baru tampil tanpa reviewer', function () {
    $fx = monevFixture();
    $operator = monevManager();
    assignCycle($operator, $fx, 'monev1', monevCycleReviewer());
    monevReportOf($fx, 'monev1')->update(['is_submitted' => true]);

    $this->actingAs($operator)->get(route('monev.manage.show', $fx['parent']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cycles.0.reviewers', 1)
            ->where('cycles.1.cycle.state', 'open')
            ->where('cycles.1.reviewers', [])
            ->where('cycles.1.canAssign', true));
});

it('[MON-REVIEW-06] saran berisi reviewer pengajuan awal yang masih aktif', function () {
    $fx = monevFixture();
    $awal = monevCycleReviewer();
    SubmissionReviewer::create([
        'form_submission_id' => $fx['parent']->id,
        'reviewer_id' => $awal->id,
        'status' => 'active',
        'evaluation_status' => 'completed',
    ]);

    $this->actingAs(monevManager())->get(route('monev.manage.show', $fx['parent']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('suggestedReviewerIds', [$awal->id]));
});

it('[MON-REVIEW-05] reviewer lewat masa tugas tidak disarankan tapi reviewer lain bisa dicari', function () {
    $fx = monevFixture();
    $habis = monevCycleReviewer();
    AssignReviewer::run($fx['parent'], $habis);
    $habis->update(['end_date' => now()->subMonth()]);
    $lain = monevCycleReviewer();
    $lain->user->update(['name' => 'Dr. Rina Wijaya']);

    $this->actingAs(monevManager())
        ->get(route('monev.manage.show', ['submission' => $fx['parent'], 'reviewer_cycle' => $fx['monev1']->id, 'reviewer_search' => 'rina']), [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Monev/ManageShowPage',
            'X-Inertia-Partial-Data' => 'reviewerCandidates',
        ])
        ->assertJsonPath('props.reviewerCandidates.0.reviewerId', $lain->id)
        ->assertJsonPath('props.reviewerCandidates.0.blockedReason', null)
        ->assertJsonCount(1, 'props.reviewerCandidates');

    $this->actingAs(monevManager())->get(route('monev.manage.show', $fx['parent']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('suggestedReviewerIds', []));
    // Kandidat siklus tanpa laporan tidak membuat laporan (GET tidak mengubah state).
    expect(monevReportOf($fx, 'monev1'))->toBeNull();
});

it('[MON-REVIEW-15] anggota tim induk tidak bisa menjadi reviewer laporan monev', function () {
    $fx = monevFixture();
    $reviewer = monevCycleReviewer();
    ResearchMember::factory()->coInvestigator()->create([
        'form_submission_id' => $fx['parent']->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $reviewer->user_id])->id,
    ]);

    assignCycle(monevManager(), $fx, 'monev1', $reviewer)->assertSessionHasErrors('user_id');

    expect(SubmissionReviewer::query()->where('reviewer_id', $reviewer->id)->exists())->toBeFalse();
});

it('perpanjang tenggat laporan membuka kembali siklus tertutup', function () {
    $fx = monevFixture();
    $fx['monev1']->submissionDate->update(['datetime' => now()->subDay()]);
    $operator = monevManager();
    openCycle($operator, $fx, 'monev1');
    $laporan = monevReportOf($fx, 'monev1');

    expect(MonevCycles::run($fx['parent'])[0]->state)->toBe(MonevCycleState::Closed);
    $this->actingAs($operator)->get(route('monev.manage.show', $fx['parent']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cycles.0.canExtend', true)
            ->where('cycles.0.phaseAccess.is_open', false)
            ->where('cycles.0.overrideTarget.phaseLabel', fn (?string $label) => $label !== null));

    $this->actingAs($operator)->post(route('admin.submissions.overrides.store', $laporan), [
        'form_phase_detail_id' => $fx['monev1']->id,
        'reason' => 'Lapangan terkendala banjir.',
        'expires_at' => now()->addWeek()->toDateTimeString(),
    ])->assertSessionHasNoErrors();

    expect(MonevCycles::run($fx['parent'])[0]->state)->toBe(MonevCycleState::Open);
});

it('detail operator untuk laporan anak dialihkan ke aktivasi siklus', function () {
    $fx = monevFixture();
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    $this->actingAs(monevManager())->get(route('admin.submissions.show', $laporan))
        ->assertRedirect(route('monev.manage.show', $fx['parent']));
});
