<?php

// OPERATOR-07-01 tab Tindak Lanjut (W152) — tiga daftar tindakan berikutnya, terlama dulu,
// maks lima baris; nama pengajuan hanya bagi submissions.view-all (W148, BR-SM-07).

use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\FollowUpListData;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchOutput;
use App\Models\User;
use App\Queries\Reporting\FollowUpLists;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Submitted;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/** @return array<string, FollowUpListData> */
function followUps(bool $withItems = true): array
{
    return collect(FollowUpLists::run(new StatisticsFilters(null, null, null), $withItems))->keyBy('key')->all();
}

it('daftar tindak lanjut terurut terlama dan dibatasi lima', function () {
    $oldest = reportedSubmission(['status' => Submitted::class], now()->subDays(30)->toDateTimeString());
    foreach ([3, 20, 9, 1, 14, 6] as $days) {
        reportedSubmission(['status' => Submitted::class], now()->subDays($days)->toDateTimeString());
    }
    reportedSubmission(['status' => NeedsRevision::class], now()->subDays(4)->toDateTimeString());

    $lists = followUps();
    $waiting = $lists['awaitingReviewer'];

    expect(array_keys($lists))->toBe(['awaitingReviewer', 'awaitingRevision', 'approvedWithoutOutput'])
        ->and($waiting->title)->toBe('Menunggu Penugasan Reviewer')
        ->and($waiting->hint)->toBe('Tugaskan reviewer dari detail pengajuan.')
        ->and($waiting->count)->toBe(7)
        ->and($waiting->items)->toHaveCount(5)
        ->and(array_map(fn ($item) => $item->ageDays, $waiting->items))->toBe([30, 20, 14, 9, 6])
        ->and($waiting->items[0]->toArray())->toMatchArray([
            'id' => $oldest->id,
            'title' => $oldest->form->title,
            'leader' => $oldest->submittedBy->name,
            'href' => route('admin.submissions.show', $oldest),
        ])
        ->and($waiting->href)->toBe(route('admin.submissions.index', ['status' => 'submitted']))
        ->and($lists['awaitingRevision']->count)->toBe(1)
        ->and($lists['awaitingRevision']->href)->toBe(route('admin.submissions.index', ['status' => 'needs_revision']));
});

it('disetujui tanpa luaran tidak memuat yang sudah punya luaran', function () {
    OutputTypeDefinition::create(['code' => 'journal', 'name' => 'Artikel Jurnal', 'is_active' => true, 'fields' => []]);
    $without = reportedSubmission(['status' => Approved::class]);
    $with = reportedSubmission(['status' => Approved::class]);
    ResearchOutput::create(['form_submission_id' => $with->id, 'output_type' => 'journal', 'metadata' => [], 'created_by' => $with->submitted_by]);

    $list = followUps()['approvedWithoutOutput'];

    expect($list->title)->toBe('Disetujui tanpa Luaran')
        ->and($list->hint)->toBe('Ingatkan tim untuk mendaftarkan luaran.')
        ->and($list->count)->toBe(1)
        ->and(array_map(fn ($item) => $item->id, $list->items))->toBe([$without->id])
        ->and($list->href)->toBeNull();
});

it('tanpa submissions.view-all hanya angka yang dikirim', function () {
    reportedSubmission(['status' => Submitted::class]);
    reportedSubmission(['status' => NeedsRevision::class]);
    reportedSubmission(['status' => Approved::class]);
    $auditor = User::factory()->create();
    $auditor->givePermissionTo('reporting.view-audit-log');

    $this->actingAs($auditor)
        ->get(route('reporting.stats', ['tab' => 'follow-up']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('followUp', 3)
            ->where('followUp.0.count', 1)
            ->where('followUp.0.items', [])
            ->where('followUp.0.href', null)
            ->where('followUp.1.items', [])
            ->where('followUp.2.count', 1)
            ->where('followUp.2.items', []));
});
