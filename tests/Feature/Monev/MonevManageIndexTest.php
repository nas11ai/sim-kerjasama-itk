<?php

// tests/Feature/Monev/MonevManageIndexTest.php
//
// OPERATOR-05-01 — Manajemen Siklus Monev: induk APPROVED yang punya siklus (W125),
// dijaga submissions.view-all (W129), status dari siklus berjalan.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\Monev\OpenMonevCycle;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function monevBoardOperator(): User
{
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    return $operator;
}

/** @return list<int> */
function monevBoardIds(array $query = []): array
{
    $ids = [];
    test()->actingAs(monevBoardOperator())
        ->get(route('monev.manage.index', $query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids = array_column($page->toArray()['props']['rows']['data'], 'id');
        });

    return $ids;
}

/** @return array<int, string> id induk → status papan */
function monevBoardStatuses(): array
{
    $statuses = [];
    test()->actingAs(monevBoardOperator())
        ->get(route('monev.manage.index'))
        ->assertInertia(function (AssertableInertia $page) use (&$statuses) {
            foreach ($page->toArray()['props']['rows']['data'] as $row) {
                $statuses[$row['id']] = $row['status'];
            }
        });

    return $statuses;
}

function monevBoardSubmitted(array $fx, string $cycle): FormSubmission
{
    $laporan = StartChildSubmission::run($fx['parent'], $fx[$cycle], $fx['owner']);
    $laporan->update(['is_submitted' => true]);

    return $laporan;
}

it('kontrak manajemen siklus', function () {
    $fx = monevFixture();

    $this->actingAs(monevBoardOperator())->get(route('monev.manage.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Monev/ManageIndexPage')
            ->where('filters', ['search' => '', 'period' => '', 'scheme' => ''])
            ->has('periodOptions')
            ->has('schemeOptions')
            ->has('rows.data', 1)
            ->has('rows.data.0', fn (AssertableInertia $row) => $row
                ->where('id', $fx['parent']->id)
                ->where('title', $fx['parent']->form->title)
                ->where('leader', $fx['owner']->name)
                ->where('currentCycle', 'Monev I')
                ->where('status', 'not_opened')
                ->has('scheme')));
});

it('wewenang: operator 200, peneliti 403', function () {
    $fx = monevFixture();
    $fx['owner']->assignRole('researcher');

    $this->actingAs(monevBoardOperator())->get(route('monev.manage.index'))->assertOk();
    $this->actingAs($fx['owner'])->get(route('monev.manage.index'))->assertForbidden();
});

it('hanya induk APPROVED dengan siklus', function () {
    $approved = monevFixture();
    $review = monevFixture(parentStatus: UnderReview::class);
    $withdrawn = monevFixture(parentStatus: Withdrawn::class);
    $laporan = StartChildSubmission::run($approved['parent'], $approved['monev1'], $approved['owner']);

    // Induk APPROVED yang detailnya tanpa detail sesudahnya: tidak punya siklus (W125).
    $lone = FormSubmission::factory()->create([
        'form_id' => $approved['parent']->form_id,
        'submitted_by' => $approved['owner']->id,
        'form_phase_detail_id' => $approved['monev2']->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    expect(monevBoardIds())->toBe([$approved['parent']->id])
        ->not->toContain($review['parent']->id, $withdrawn['parent']->id, $laporan->id, $lone->id);
});

it('status siklus berjalan: Belum Aktif, Laporan Masuk, Selesai', function () {
    $belum = monevFixture();
    $masuk = monevFixture(monev1NeedsReview: true);
    // Evaluasi yang belum tuntas menahan laporan di Submitted (canProceed).
    SubmissionReviewer::create([
        'form_submission_id' => monevBoardSubmitted($masuk, 'monev1')->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'status' => 'active',
        'evaluation_status' => 'pending',
    ]);
    $selesai = monevFixture();
    monevBoardSubmitted($selesai, 'monev1');
    monevBoardSubmitted($selesai, 'monev2');

    expect(monevBoardStatuses())->toBe([
        $selesai['parent']->id => 'completed',
        $masuk['parent']->id => 'report_in',
        $belum['parent']->id => 'not_opened',
    ]);

    $this->actingAs(monevBoardOperator())->get(route('monev.manage.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.currentCycle', null)
            ->where('rows.data.1.currentCycle', 'Monev I'));
});

it('status siklus berjalan Menunggu Laporan setelah siklus diaktifkan', function () {
    $fx = monevFixture();
    OpenMonevCycle::run($fx['parent'], $fx['monev1'], monevBoardOperator());

    expect(monevBoardStatuses())->toBe([$fx['parent']->id => 'awaiting_report']);
});

it('filter periode, skema, dan pencarian judul atau ketua', function () {
    $a = monevFixture();
    $b = monevFixture();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();
    $a['parent']->update(['submission_period_id' => $period->id, 'scheme_id' => $scheme->id]);
    $a['parent']->form->update(['title' => 'Smart Farming Hidroponik Kampus']);
    $b['owner']->update(['name' => 'Dr. Andi Mappa']);

    expect(monevBoardIds(['period' => (string) $period->id]))->toBe([$a['parent']->id])
        ->and(monevBoardIds(['scheme' => (string) $scheme->id]))->toBe([$a['parent']->id])
        ->and(monevBoardIds(['search' => 'hidroponik']))->toBe([$a['parent']->id])
        ->and(monevBoardIds(['search' => 'andi mappa']))->toBe([$b['parent']->id])
        ->and(monevBoardIds(['period' => 'bukan-angka']))->toHaveCount(2);
});
