<?php

// OPERATOR-07-01 tab Ringkasan — definisi KPI (W150) dan isi panel (W151). Setiap angka
// berangkat dari basis W149, jadi drill-down membuka daftar yang barisnya sama.

use App\Data\Reporting\StatisticsFilters;
use App\Models\BudgetLineItem;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchOutput;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\User;
use App\Queries\Reporting\StatisticsOverview;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/** @return array<string, mixed> */
function overview(?int $year = null, ?int $scheme = null, bool $withLinks = true): array
{
    return StatisticsOverview::run(new StatisticsFilters($year, null, $scheme), $withLinks);
}

/** @return array<string, mixed> KPI berkunci `key` */
function kpi(array $overview, string $key): array
{
    return collect($overview['kpis'])->firstWhere('key', $key)->toArray();
}

function statusCount(array $overview, string $key): int
{
    return collect($overview['byStatus'])->firstWhere('key', $key)->count;
}

it('angka per status sama dengan baris daftar drill-down', function () {
    $dipa = Scheme::factory()->create(['name' => 'DIPA']);
    $twoControls = reportedSubmission(['scheme_id' => $dipa->id, 'status' => Approved::class]);
    FormAccessControl::create(['form_id' => $twoControls->form_id, 'permission' => 'submissions.create', 'organization_id' => Organization::create(['name' => 'Prodi A', 'type' => 'study_program'])->id]);
    FormAccessControl::create(['form_id' => $twoControls->form_id, 'permission' => 'submissions.create', 'organization_id' => Organization::create(['name' => 'Prodi B', 'type' => 'study_program'])->id]);
    $facultyForm = reportedSubmission(['scheme_id' => $dipa->id, 'status' => Approved::class]);
    FormAccessControl::create(['form_id' => $facultyForm->form_id, 'permission' => 'submissions.create', 'organization_id' => Organization::create(['name' => 'Fakultas A', 'type' => 'faculty'])->id]);
    reportedSubmission(['scheme_id' => $dipa->id, 'status' => Approved::class], '2025-06-01 08:00:00');
    reportedSubmission(['scheme_id' => $dipa->id, 'status' => Draft::class, 'is_submitted' => false]);
    reportedSubmission(['scheme_id' => $dipa->id, 'status' => Approved::class, 'parent_submission_id' => $twoControls->id]);

    $result = overview(2026, $dipa->id);
    $approved = collect($result['byStatus'])->firstWhere('key', 'approved');

    expect($approved->count)->toBe(2)
        ->and($approved->href)->toBe(route('admin.submissions.index', ['status' => 'approved', 'year' => 2026, 'scheme' => $dipa->id]))
        ->and(kpi($result, 'submissions')['value'])->toBe('2');

    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $this->actingAs($operator)->get($approved->href)
        ->assertInertia(fn (Assert $page) => $page->where('submissions.total', 2));
});

it('statistik kosong tidak membagi nol', function () {
    $result = overview(2026);

    expect(collect($result['byStatus'])->pluck('count')->unique()->all())->toBe([0])
        ->and(collect($result['byStatus'])->pluck('share')->unique()->all())->toBe([0.0])
        ->and(kpi($result, 'approvalRate')['value'])->toBe('—')
        ->and(kpi($result, 'approvedBudget')['value'])->toBe('Rp 0')
        ->and($result['byFaculty'])->toBe([])
        ->and($result['byOutputType'])->toBe([])
        ->and($result['bySchemeYear']->years)->toBe([]);
});

it('status berurutan menurut alur dan berbagi dari total', function () {
    reportedSubmission(['status' => Approved::class]);
    reportedSubmission(['status' => UnderReview::class]);
    reportedSubmission(['status' => UnderReview::class]);
    reportedSubmission(['status' => Approved::class]);

    $byStatus = collect(overview(2026)['byStatus']);

    expect($byStatus->pluck('label')->all())->toBe(['Menunggu Review', 'Sedang Direview', 'Perlu Revisi', 'Diajukan Ulang', 'Disetujui', 'Ditolak', 'Ditarik Kembali'])
        ->and($byStatus->firstWhere('key', 'under_review')->share)->toBe(0.5);
});

it('tingkat persetujuan tidak menghitung yang ditarik', function () {
    foreach ([Approved::class, Approved::class, Approved::class, Rejected::class, Withdrawn::class, Withdrawn::class] as $state) {
        reportedSubmission(['status' => $state]);
    }

    expect(kpi(overview(2026), 'approvalRate')['value'])->toBe('75%');
});

it('anggaran disetujui menjumlah item yang tidak terhapus pada pengajuan disetujui', function () {
    $approved = reportedSubmission(['status' => Approved::class]);
    $underReview = reportedSubmission(['status' => UnderReview::class]);
    BudgetLineItem::factory()->create(['form_submission_id' => $approved->id, 'volume' => 1, 'unit_price' => 4000000, 'total' => 4000000]);
    BudgetLineItem::factory()->create(['form_submission_id' => $approved->id, 'volume' => 1, 'unit_price' => 2500000, 'total' => 2500000]);
    BudgetLineItem::factory()->create(['form_submission_id' => $approved->id, 'volume' => 1, 'unit_price' => 9000000, 'total' => 9000000])->delete();
    BudgetLineItem::factory()->create(['form_submission_id' => $underReview->id, 'volume' => 1, 'unit_price' => 5000000, 'total' => 5000000]);

    expect(kpi(overview(2026), 'approvedBudget')['value'])->toBe('Rp 6.500.000');
});

it('reviewer aktif mengikuti masa tugas dan status aktif', function () {
    Reviewer::factory()->create(['reviewer_type' => 'internal', 'start_date' => now()->subMonth()]);
    Reviewer::factory()->create(['reviewer_type' => 'external', 'start_date' => now()->subMonth()]);
    Reviewer::factory()->create(['reviewer_type' => 'internal', 'start_date' => now()->subYear(), 'end_date' => now()->subDay()]);
    Reviewer::factory()->create(['reviewer_type' => 'internal', 'user_id' => User::factory()->create(['is_active' => false])->id]);

    $reviewers = kpi(overview(2026), 'activeReviewers');

    expect($reviewers['value'])->toBe('2')
        ->and($reviewers['meta'])->toBe('Internal 1 · Eksternal 1');
});

it('skema per tahun memuat tiga tahun terakhir dan mengabaikan filter tahun', function () {
    $dipa = Scheme::factory()->create(['name' => 'DIPA']);
    foreach (range(2022, 2026) as $year) {
        reportedSubmission(['scheme_id' => $dipa->id], "{$year}-04-01 08:00:00");
    }
    reportedSubmission(['scheme_id' => $dipa->id], '2026-05-01 08:00:00');

    $chart = overview(2024)['bySchemeYear'];

    expect($chart->years)->toBe([2024, 2025, 2026])
        ->and($chart->rows)->toBe([['scheme' => 'DIPA', 'counts' => ['2024' => 1, '2025' => 1, '2026' => 2]]]);
});

it('lebih dari sepuluh unit dilipat menjadi Lainnya', function () {
    foreach (range(1, 12) as $i) {
        $prodi = Organization::create(['name' => sprintf('Prodi %02d', $i), 'type' => 'study_program']);
        foreach (range(1, $i <= 10 ? 2 : 1) as $ignored) {
            reportedSubmission(unit: $prodi);
        }
    }

    $rows = overview(2026)['byStudyProgram'];

    expect($rows)->toHaveCount(11)
        ->and($rows[0]->label)->toBe('Prodi 01')
        ->and($rows[10]->label)->toBe('Lainnya')
        ->and($rows[10]->count)->toBe(2);
});

it('luaran per tipe memakai nama tipe dan hanya luaran pengajuan di basis', function () {
    OutputTypeDefinition::create(['code' => 'journal', 'name' => 'Artikel Jurnal', 'is_active' => true, 'fields' => []]);
    $in = reportedSubmission(['status' => Approved::class]);
    $old = reportedSubmission(['status' => Approved::class], '2025-02-01 08:00:00');
    foreach ([$in, $in, $old] as $submission) {
        ResearchOutput::create(['form_submission_id' => $submission->id, 'output_type' => 'journal', 'metadata' => [], 'created_by' => $submission->submitted_by]);
    }

    $result = overview(2026);

    expect($result['byOutputType'][0]->label)->toBe('Artikel Jurnal')
        ->and($result['byOutputType'][0]->count)->toBe(2)
        ->and(kpi($result, 'outputs')['value'])->toBe('2');
});

it('tanpa view-all tidak ada tautan drill-down', function () {
    reportedSubmission(['status' => Approved::class]);

    $result = overview(2026, withLinks: false);

    expect(kpi($result, 'submissions')['href'])->toBeNull()
        ->and(collect($result['byStatus'])->pluck('href')->unique()->all())->toBe([null]);
});

it('tab Ringkasan mengirim KPI dan keempat panel', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)->get(route('reporting.stats'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('kpis', 5)
            ->has('byStatus', 7)
            ->has('bySchemeYear.years')
            ->has('byFaculty')
            ->has('byStudyProgram')
            ->has('byOutputType')
            ->where('kpis.0.href', route('admin.submissions.index', ['year' => now()->year])));
});
