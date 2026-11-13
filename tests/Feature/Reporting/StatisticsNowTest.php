<?php

// OPERATOR-07-01 tab Saat Ini (W152) — pengajuan berjalan per tahap beserta umur terlama,
// status siklus monev (W153), cakupan dan beban reviewer. Keadaan sekarang: tanpa filter tahun.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\RankedCountData;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Monev\MonevCycleBoard;
use App\Queries\Reporting\MonevStatusCounts;
use App\Queries\Reporting\ReviewerCoverage;
use App\Queries\Reporting\ReviewerWorkload;
use App\Queries\Reporting\StageBacklog;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function nowFilters(): StatisticsFilters
{
    return new StatisticsFilters(null, null, null);
}

function nowSeat(FormSubmission $submission, Reviewer $reviewer, string $status = 'active', string $evaluation = 'pending'): void
{
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => $status,
        'evaluation_status' => $evaluation,
    ]);
}

/** @param  list<RankedCountData>  $rows @return array<string, int> */
function nowCounts(array $rows): array
{
    return collect($rows)->mapWithKeys(fn (RankedCountData $row) => [$row->label => $row->count])->all();
}

it('tahap berjalan menghitung umur terlama sejak submit pertama', function () {
    reportedSubmission(['status' => Submitted::class], now()->subDays(12)->toDateTimeString());
    reportedSubmission(['status' => Submitted::class], now()->subDays(3)->toDateTimeString());
    reportedSubmission(['status' => Approved::class], now()->subDays(40)->toDateTimeString());

    $stages = collect(StageBacklog::run(nowFilters(), true))->keyBy('key');

    expect($stages->keys()->all())->toBe(['submitted', 'under_review', 'needs_revision', 'resubmitted'])
        ->and($stages['submitted']->count)->toBe(2)
        ->and($stages['submitted']->oldestAgeDays)->toBe(12)
        ->and($stages['submitted']->label)->toBe('Menunggu Penilaian')
        ->and($stages['submitted']->href)->toBe(route('admin.submissions.index', ['status' => 'submitted']))
        ->and($stages['under_review']->count)->toBe(0)
        ->and($stages['under_review']->oldestAgeDays)->toBeNull();
});

it('tahap berjalan tanpa submissions.view-all tidak menaut', function () {
    reportedSubmission(['status' => Submitted::class]);

    expect(StageBacklog::run(nowFilters(), false)[0]->href)->toBeNull();
});

it('saat ini tidak memakai filter tahun', function () {
    reportedSubmission(['status' => UnderReview::class], '2024-05-01 08:00:00');
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $now = function (array $query) use ($operator): array {
        $props = [];
        $this->actingAs($operator)
            ->get(route('reporting.stats', ['tab' => 'now', ...$query]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props']['now'];
            });

        return $props;
    };

    $all = $now([]);

    expect($now(['year' => 2020]))->toBe($all)
        ->and(collect($all['backlog'])->firstWhere('key', 'under_review')['count'])->toBe(1);
});

it('status monev agregat sama dengan papan monev', function () {
    monevFixture();
    $masuk = monevFixture(monev1NeedsReview: true);
    $laporan = StartChildSubmission::run($masuk['parent'], $masuk['monev1'], $masuk['owner']);
    $laporan->update(['is_submitted' => true]);
    nowSeat($laporan, Reviewer::factory()->create());
    $selesai = monevFixture();
    foreach (['monev1', 'monev2'] as $cycle) {
        StartChildSubmission::run($selesai['parent'], $selesai[$cycle], $selesai['owner'])->update(['is_submitted' => true]);
    }

    $board = collect(MonevCycleBoard::run(['search' => '', 'period' => '', 'scheme' => ''])['rows']->items())
        ->countBy(fn ($row) => $row->status->label())
        ->all();
    $counts = MonevStatusCounts::run(nowFilters(), true);

    expect(array_column(array_map(fn ($r) => $r->toArray(), $counts), 'label'))
        ->toBe(['Belum Aktif', 'Menunggu Laporan', 'Laporan Masuk', 'Selesai'])
        ->and(array_filter(nowCounts($counts)))->toEqual($board)
        ->and($counts[0]->href)->toBe(route('monev.manage.index'));
});

it('beban reviewer menghitung evaluasi aktif yang belum selesai', function () {
    $sibuk = Reviewer::factory()->create(['user_id' => User::factory()->create(['name' => 'Dr. Sari'])->id]);
    foreach (range(1, 3) as $i) {
        nowSeat(FormSubmission::factory()->create(), $sibuk);
    }
    nowSeat(FormSubmission::factory()->create(), $sibuk, evaluation: 'completed');
    nowSeat(FormSubmission::factory()->create(), $sibuk, status: 'replaced');
    Reviewer::factory()->create(['reviewer_type' => 'external']);
    $lewat = Reviewer::factory()->create(['start_date' => now()->subYears(2), 'end_date' => now()->subDay()]);
    nowSeat(FormSubmission::factory()->create(), $lewat);

    $workload = ReviewerWorkload::run();

    expect(nowCounts($workload->rows))->toBe(['Dr. Sari (Internal)' => 3])
        ->and($workload->rows[0]->href)->toBeNull()
        ->and($workload->idleCount)->toBe(1);
});

it('cakupan reviewer menghitung reviewer aktif per pengajuan berjalan', function () {
    $reviewers = Reviewer::factory()->count(3)->create();
    reportedSubmission(['status' => Submitted::class]);
    $satu = reportedSubmission(['status' => UnderReview::class]);
    nowSeat($satu, $reviewers[0]);
    nowSeat($satu, $reviewers[1], status: 'replaced');
    $tiga = reportedSubmission(['status' => NeedsRevision::class]);
    $reviewers->each(fn (Reviewer $r) => nowSeat($tiga, $r));
    $approved = reportedSubmission(['status' => Approved::class]);
    nowSeat($approved, $reviewers[0]);

    expect(nowCounts(ReviewerCoverage::run(nowFilters())))->toBe([
        'Tanpa penilai' => 1,
        '1 penilai' => 1,
        '2 penilai' => 0,
        '3+ penilai' => 1,
    ]);
});
