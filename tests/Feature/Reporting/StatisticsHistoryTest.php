<?php

// OPERATOR-07-01 tab Riwayat (W152) — tren bulanan pengajuan disubmit dan luaran terdaftar,
// serta kinerja per skema dengan definisi KPI yang sama dengan Ringkasan (W150).

use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\SchemePerformanceData;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchOutput;
use App\Models\Scheme;
use App\Models\User;
use App\Queries\Reporting\MonthlyTrend;
use App\Queries\Reporting\SchemePerformance;
use App\Queries\Reporting\StatisticsOverview;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function historyOutput(FormSubmission $submission, string $createdAt): void
{
    OutputTypeDefinition::firstOrCreate(['code' => 'journal'], ['name' => 'Artikel Jurnal', 'is_active' => true, 'fields' => []]);
    test()->travelTo($createdAt);
    ResearchOutput::create(['form_submission_id' => $submission->id, 'output_type' => 'journal', 'metadata' => [], 'created_by' => $submission->submitted_by]);
    test()->travelBack();
}

/** @return array<string, int> */
function historyPoints(array $trend): array
{
    return collect($trend['points'])->pluck('count', 'label')->all();
}

it('tren bulanan mengisi bulan kosong dengan nol', function () {
    $maret = reportedSubmission([], '2026-03-05 08:00:00');
    reportedSubmission([], '2026-03-20 08:00:00');
    reportedSubmission([], '2026-07-01 08:00:00');
    reportedSubmission([], '2025-03-01 08:00:00');
    historyOutput($maret, '2026-09-10 08:00:00');
    historyOutput($maret, '2025-12-10 08:00:00');

    $trend = MonthlyTrend::run(new StatisticsFilters(2026, null, null));
    $submitted = historyPoints($trend['submitted']->toArray());

    expect($trend['submitted']->unit)->toBe('month')
        ->and(array_keys($submitted))->toBe(['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'])
        ->and($submitted['Mar'])->toBe(2)
        ->and($submitted['Jul'])->toBe(1)
        ->and(array_sum($submitted))->toBe(3)
        ->and(historyPoints($trend['outputs']->toArray()))->toMatchArray(['Sep' => 1, 'Des' => 0]);
});

it('tren tanpa tahun dikelompokkan per tahun', function () {
    reportedSubmission([], '2025-05-01 08:00:00');
    reportedSubmission([], '2026-05-01 08:00:00');
    reportedSubmission([], '2026-06-01 08:00:00');

    $trend = MonthlyTrend::run(new StatisticsFilters(null, null, null));

    expect($trend['submitted']->unit)->toBe('year')
        ->and(historyPoints($trend['submitted']->toArray()))->toBe(['2025' => 1, '2026' => 2])
        ->and($trend['outputs']->points)->toBe([]);
});

it('kinerja skema memakai definisi KPI yang sama', function () {
    $dipa = Scheme::factory()->create(['name' => 'DIPA']);
    $mandiri = Scheme::factory()->create(['name' => 'Mandiri']);
    $approved = reportedSubmission(['scheme_id' => $dipa->id, 'status' => Approved::class]);
    reportedSubmission(['scheme_id' => $dipa->id, 'status' => Rejected::class]);
    reportedSubmission(['scheme_id' => $dipa->id, 'status' => Withdrawn::class]);
    reportedSubmission(['scheme_id' => $mandiri->id, 'status' => UnderReview::class]);
    reportedSubmission(['scheme_id' => $dipa->id, 'status' => Approved::class], '2025-04-01 08:00:00');
    BudgetLineItem::factory()->create(['form_submission_id' => $approved->id, 'volume' => 1, 'unit_price' => 4000000, 'total' => 4000000]);
    BudgetLineItem::factory()->create(['form_submission_id' => $approved->id, 'volume' => 1, 'unit_price' => 9000000, 'total' => 9000000])->delete();
    $filters = new StatisticsFilters(2026, null, null);

    $rows = collect(SchemePerformance::run($filters, true))->keyBy('scheme');
    $overview = overviewKpis($filters);

    expect($rows->keys()->all())->toBe(['DIPA', 'Mandiri'])
        ->and($rows['DIPA']->toArray())->toMatchArray([
            'submitted' => 3, 'approved' => 1, 'rejected' => 1, 'withdrawn' => 1,
            'approvalRate' => '50%', 'approvedBudget' => 'Rp 4.000.000',
            'href' => route('admin.submissions.index', ['scheme' => $dipa->id, 'year' => 2026]),
        ])
        ->and($rows['Mandiri']->approvalRate)->toBe('—')
        ->and($rows->sum(fn (SchemePerformanceData $row) => $row->submitted))->toBe((int) $overview['submissions'])
        ->and($rows['DIPA']->approvedBudget)->toBe($overview['approvedBudget'])
        ->and($rows['DIPA']->approvalRate)->toBe($overview['approvalRate'])
        ->and(SchemePerformance::run($filters, false)[0]->href)->toBeNull();
});

it('tab riwayat mengirim tren dan kinerja skema', function () {
    $operator = statsHistoryOperator();
    reportedSubmission([], now()->startOfYear()->addMonth()->toDateTimeString());

    $this->actingAs($operator)
        ->get(route('reporting.stats', ['tab' => 'history']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('history.submitted.unit', 'month')
            ->has('history.submitted.points', 12)
            ->where('history.submitted.points.1.count', 1)
            ->has('history.outputs.points', 12)
            ->has('history.schemes', 1));
});

/** @return array<string, string> nilai KPI Ringkasan berkunci `key` */
function overviewKpis(StatisticsFilters $filters): array
{
    return collect(StatisticsOverview::run($filters, false)['kpis'])->pluck('value', 'key')->all();
}

function statsHistoryOperator(): User
{
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    return $operator;
}
