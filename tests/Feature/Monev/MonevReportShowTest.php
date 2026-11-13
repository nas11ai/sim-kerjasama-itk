<?php

// tests/Feature/Monev/MonevReportShowTest.php
//
// RESEARCHER-05-03 — detail laporan monev, dibaca siapa pun yang lolos isAccessibleBy:
// ketua, anggota tim induk (W123), reviewer aktif laporan, Operator.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Withdrawn;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->fx = monevFormFixture();
    $this->fx['owner']->assignRole('researcher');
    $this->report = StartChildSubmission::run($this->fx['parent'], $this->fx['monev1'], $this->fx['owner']);
});

function monevShowMember(FormSubmission $parent): User
{
    $user = User::factory()->create();
    $user->assignRole('researcher');
    ResearchMember::factory()->coInvestigator()->create([
        'form_submission_id' => $parent->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
    ]);

    return $user;
}

function monevSubmitReport(FormSubmission $report, int $fieldId): void
{
    test()->post(route('monev.reports.submit', $report), [
        'last_updated_at' => $report->fresh()->updated_at?->toISOString(),
        'responses' => [['form_field_id' => $fieldId, 'value' => 'Capaian 45%']],
    ]);
}

it('kontrak detail laporan', function () {
    $this->actingAs($this->fx['owner']);
    monevSubmitReport($this->report, $this->fx['summary']->id);

    $this->get(route('monev.reports.show', $this->report))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Monev/ReportShowPage')
            ->where('report.cycleName', 'Monev I')
            ->where('cycle.state', 'done')
            ->whereNot('cycle.submittedAt', null)
            ->where('status.key', 'submitted')
            ->has('answers', 2)
            ->has('scores', fn (AssertableInertia $scores) => $scores->hasAll(['visible', 'reason', 'scores']))
            ->has('threads', 0)
            ->where('parentWithdrawn', false)
            ->where('can', ['fill' => false, 'reply' => true, 'resolveThreads' => false]));
});

it('wewenang: ketua, anggota, reviewer laporan, Operator 200; peneliti lain 403', function () {
    $reviewerUser = verifiedResearcher();
    SubmissionReviewer::create([
        'form_submission_id' => $this->report->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $reviewerUser->id])->id,
        'status' => 'active',
        'evaluation_status' => 'pending',
    ]);
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $outsider = User::factory()->create();
    $outsider->assignRole('researcher');

    foreach ([$this->fx['owner'], monevShowMember($this->fx['parent']), $reviewerUser, $operator] as $reader) {
        $this->actingAs($reader)->get(route('monev.reports.show', $this->report))->assertOk();
    }
    $this->actingAs($outsider)->get(route('monev.reports.show', $this->report))->assertForbidden();
});

it('[MON-CYCLE-11] laporan terkirim pada induk tarikan tetap terbaca', function () {
    $this->actingAs($this->fx['owner']);
    monevSubmitReport($this->report, $this->fx['summary']->id);
    $this->fx['parent']->status->transitionTo(Withdrawn::class);

    $this->get(route('monev.reports.show', $this->report))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('parentWithdrawn', true));
});

it('[MON-CYCLE-15] anggota tim induk membaca laporan monev', function () {
    $ani = monevShowMember($this->fx['parent']);

    $this->actingAs($ani)->get(route('monev.reports.show', $this->report))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.fill', false)
            ->where('can.reply', false));
});

it('[MON-REVIEW-07] skor tampil begitu reviewer submit', function () {
    $fx = monevFixture(monev1NeedsReview: true);
    $fx['owner']->assignRole('researcher');
    ['laporan' => $laporan] = monevReportWithEvaluation($fx, 'submitted');

    $this->actingAs($fx['owner'])->get(route('monev.reports.show', $laporan))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('scores.visible', true));
});

it('[MON-REVIEW-08] skor tertutup sebelum reviewer submit', function () {
    $fx = monevFixture(monev1NeedsReview: true);
    $fx['owner']->assignRole('researcher');
    ['laporan' => $laporan] = monevReportWithEvaluation($fx, 'draft');

    $this->actingAs($fx['owner'])->get(route('monev.reports.show', $laporan))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scores.visible', false)
            ->where('scores.reason', 'Hasil evaluasi akan tampil begitu penilai menyelesaikan penilaiannya.'));
});

it('draft milik ketua menawarkan isi lanjutan', function () {
    $this->actingAs($this->fx['owner'])->get(route('monev.reports.show', $this->report))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.fill', true));
});

it('detail pengajuan untuk laporan anak dialihkan ke detail laporan', function () {
    $this->actingAs($this->fx['owner'])->get(route('user.submissions.show', $this->report))
        ->assertRedirect(route('monev.reports.show', $this->report));
});

it('submit mengarah ke detail laporan', function () {
    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.submit', $this->report), [
            'last_updated_at' => $this->report->fresh()->updated_at?->toISOString(),
            'responses' => [['form_field_id' => $this->fx['summary']->id, 'value' => 'Capaian 45%']],
        ])
        ->assertRedirect(route('monev.reports.show', $this->report))
        ->assertSessionHas('success', 'Laporan Monev I dikirim.');

    $this->get(route('monev.reports.edit', $this->report))
        ->assertRedirect(route('monev.reports.show', $this->report));
});
