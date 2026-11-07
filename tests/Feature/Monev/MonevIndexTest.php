<?php

// tests/Feature/Monev/MonevIndexTest.php
//
// RESEARCHER-05-01 — Daftar Monev peneliti (W128) dan jalur memulai laporan (BR-MON-01/02/03).

use App\Actions\FormSubmission\StartChildSubmission;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/** @return array{parent: FormSubmission, owner: User, pengajuan: FormPhaseDetail, monev1: FormPhaseDetail, monev2: FormPhaseDetail} */
function monevIndexFixture(string $status = Approved::class): array
{
    $fx = monevFixture(parentStatus: $status);
    $fx['owner']->assignRole('researcher');

    return $fx;
}

function monevIndexMember(FormSubmission $parent): User
{
    $user = User::factory()->create();
    $user->assignRole('researcher');
    $profile = UserProfile::factory()->create(['user_id' => $user->id]);
    ResearchMember::factory()->coInvestigator()->create([
        'form_submission_id' => $parent->id,
        'user_profile_id' => $profile->id,
    ]);

    return $user;
}

it('kontrak daftar monev', function () {
    $fx = monevIndexFixture();

    $this->actingAs($fx['owner'])->get(route('monev.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Monev/IndexPage')
            ->has('submissions', 1)
            ->has('submissions.0', fn (AssertableInertia $row) => $row
                ->where('id', $fx['parent']->id)
                ->hasAll(['title', 'scheme', 'status', 'canFill'])
                ->has('cycles', 2)
                ->has('cycles.0', fn (AssertableInertia $cycle) => $cycle
                    ->where('name', 'Monev I')
                    ->where('state', 'open')
                    ->hasAll(['detailId', 'order', 'needsReview', 'deadline', 'blockedReason', 'reportId', 'submittedAt', 'openedAt']))
                ->etc()));
});

it('wewenang: peneliti 200, reviewer murni 403, tamu ke login', function () {
    $fx = monevIndexFixture();
    $reviewer = User::factory()->create();
    $reviewer->assignRole('reviewer_internal');

    $this->actingAs($fx['owner'])->get(route('monev.index'))->assertOk();
    $this->actingAs($reviewer)->get(route('monev.index'))->assertForbidden();
    auth()->logout();
    $this->get(route('monev.index'))->assertRedirect(route('login'));
});

it('[MON-CYCLE-01] induk UNDER_REVIEW tidak tercantum', function () {
    $fx = monevIndexFixture(UnderReview::class);

    $this->actingAs($fx['owner'])->get(route('monev.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('submissions', 0));
});

it('[MON-CYCLE-07] dua induk APPROVED tampil masing-masing dengan siklusnya', function () {
    $fx = monevIndexFixture();
    FormSubmission::factory()->create([
        'form_id' => $fx['parent']->form_id,
        'submitted_by' => $fx['owner']->id,
        'form_phase_detail_id' => $fx['pengajuan']->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);
    StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    $this->actingAs($fx['owner'])->get(route('monev.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('submissions', 2)
            ->where('submissions.0.cycles.0.reportId', null)
            ->whereNot('submissions.1.cycles.0.reportId', null));
});

it('[MON-CYCLE-11] induk tarikan dengan laporan tercantum, tanpa laporan tidak', function () {
    $fx = monevIndexFixture();
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    $laporan->update(['is_submitted' => true]);
    $fx['parent']->status->transitionTo(Withdrawn::class);

    $kosong = monevFixture();
    $kosong['parent']->update(['submitted_by' => $fx['owner']->id]);
    $kosong['parent']->status->transitionTo(Withdrawn::class);

    $this->actingAs($fx['owner'])->get(route('monev.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('submissions', 1)
            ->where('submissions.0.id', $fx['parent']->id)
            ->where('submissions.0.cycles.1.state', 'frozen'));
});

it('anggota tim melihat siklus tanpa tombol isi', function () {
    $fx = monevIndexFixture();
    $ani = monevIndexMember($fx['parent']);

    $this->actingAs($ani)->get(route('monev.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('submissions.0.id', $fx['parent']->id)
            ->where('submissions.0.canFill', false));

    $this->actingAs($fx['owner'])->get(route('monev.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('submissions.0.canFill', true));
});

it('[MON-CYCLE-02] memulai laporan mengarah ke halaman isi', function () {
    $fx = monevIndexFixture();

    $this->actingAs($fx['owner'])
        ->post(route('monev.reports.store', [$fx['parent'], $fx['monev1']]))
        ->assertRedirect(route('monev.index'))
        ->assertSessionHasNoErrors();

    expect(FormSubmission::where('parent_submission_id', $fx['parent']->id)->sole()->submitted_by)
        ->toBe($fx['owner']->id);
});

it('[MON-CYCLE-06] memulai kedua kali mengarah ke laporan yang sama', function () {
    $fx = monevIndexFixture();

    $this->actingAs($fx['owner'])->post(route('monev.reports.store', [$fx['parent'], $fx['monev1']]));
    $this->actingAs($fx['owner'])->post(route('monev.reports.store', [$fx['parent'], $fx['monev1']]))
        ->assertSessionHasNoErrors();

    expect(FormSubmission::where('parent_submission_id', $fx['parent']->id)->count())->toBe(1);
});

it('[MON-CYCLE-03] memulai Monev II sebelum Monev I tuntas ditolak', function () {
    $fx = monevIndexFixture();

    $this->actingAs($fx['owner'])
        ->from(route('monev.index'))
        ->post(route('monev.reports.store', [$fx['parent'], $fx['monev2']]))
        ->assertRedirect(route('monev.index'))
        ->assertSessionHasErrors('form_phase_detail_id');

    expect(FormSubmission::where('parent_submission_id', $fx['parent']->id)->count())->toBe(0);
});

it('[MON-CYCLE-14] anggota tim tidak bisa memulai laporan', function () {
    $fx = monevIndexFixture();
    $ani = monevIndexMember($fx['parent']);

    $this->actingAs($ani)
        ->post(route('monev.reports.store', [$fx['parent'], $fx['monev1']]))
        ->assertForbidden();
});

it('flag navigasi hasMonevSubmission mengikuti daftar', function () {
    $fx = monevIndexFixture(UnderReview::class);

    $this->actingAs($fx['owner'])->get(route('profile.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('navigation.hasMonevSubmission', false));

    $fx['parent']->status->transitionTo(Approved::class);
    $ani = monevIndexMember($fx['parent']);

    $this->actingAs($ani)->get(route('profile.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('navigation.hasMonevSubmission', true));
});
