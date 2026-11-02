<?php

// OPERATOR-09-01 — kontrak daftar periode, filter, dan wewenang `periods.manage` (K1).

use App\Enums\PeriodStatus;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\SubmissionPeriodGate;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function periodManager(string $role = 'operator'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @param  list<string>  $offsets  waktu relatif tiap tanggal, mis. '+1 month' */
function periodWithDates(array $offsets, bool $forceClosed = false, string $name = ''): SubmissionPeriod
{
    $period = SubmissionPeriod::factory()->create(array_filter([
        'is_force_closed' => $forceClosed,
        'name' => $name,
    ], fn ($v) => $v !== ''));

    foreach ($offsets as $i => $offset) {
        $period->submissionDates()->create([
            'submission_date_label_id' => SubmissionDateLabel::firstOrCreate(['name' => 'Batas '.$i])->id,
            'datetime' => now()->modify($offset),
        ]);
    }

    return $period;
}

/** @return list<int> */
function listedPeriodIds(array $query): array
{
    $ids = [];
    test()->actingAs(periodManager())
        ->get(route('admin.submission-periods.index', $query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids = array_column($page->toArray()['props']['periods']['data'], 'id');
        });
    sort($ids);

    return $ids;
}

it('kontrak layar periode', function () {
    $phase = FormPhase::create(['title' => 'Proposal', 'is_active' => true]);
    $period = periodWithDates(['+1 month']);
    $period->submissionPeriodPhases()->create(['form_phase_id' => $phase->id]);

    $this->actingAs(periodManager())
        ->get(route('admin.submission-periods.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PeriodIndexPage')
            ->has('periods.data', 1)
            ->has('periods.data.0', fn (AssertableInertia $row) => $row
                ->where('id', $period->id)
                ->where('name', $period->name)
                ->where('status', ['value' => 'active', 'label' => 'Dibuka'])
                ->has('dates', 1)
                ->has('dates.0', fn (AssertableInertia $date) => $date
                    ->where('label', 'Batas 0')
                    ->where('passed', false)
                    ->where('phaseDetailCount', 0)
                    ->has('id')->has('at')->has('inputValue'))
                ->where('formPhaseIds', [$phase->id])
                ->where('submittedCount', 0)
                ->where('draftOwnerCount', 0)
                ->where('closable', true)
                ->where('deleteBlockers', []))
            ->where('filters', ['search' => '', 'status' => '', 'year' => ''])
            ->has('statusOptions', 4)
            ->has('yearOptions')
            ->has('labelOptions')
            ->where('phaseOptions', [['value' => (string) $phase->id, 'label' => 'Proposal']])
            ->where('can.viewSubmissions', true));
});

it('wewenang: operator 200, peneliti 403, tamu ke login', function () {
    $this->actingAs(periodManager())->get(route('admin.submission-periods.index'))->assertOk();
    $this->actingAs(periodManager('admin'))->get(route('admin.submission-periods.index'))->assertOk();
    $this->actingAs(periodManager('researcher'))->get(route('admin.submission-periods.index'))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.submission-periods.index'))->assertRedirect(route('login'));
});

it('filter status sama dengan displayStatus', function () {
    $periods = [
        periodWithDates(['+1 month'], forceClosed: true),
        periodWithDates([]),
        periodWithDates(['-1 day', '+1 month']),
        periodWithDates(['-2 days', '-1 day']),
    ];

    foreach (PeriodStatus::cases() as $status) {
        $expected = array_values(array_map(
            fn (SubmissionPeriod $p) => $p->id,
            array_filter($periods, fn (SubmissionPeriod $p) => SubmissionPeriodGate::displayStatus($p->fresh()) === $status->value),
        ));
        sort($expected);

        expect(listedPeriodIds(['status' => $status->value]))->toBe($expected, $status->value);
    }

    expect(listedPeriodIds(['status' => 'tak-dikenal']))->toHaveCount(4);
});

it('filter tahun dan pencarian nama tidak peka huruf', function () {
    $lama = periodWithDates(['-2 years'], name: 'Penelitian DIPA Lama');
    $baru = periodWithDates(['+1 month'], name: 'Pengabdian Baru');
    $year = (string) now()->modify('-2 years')->year;

    expect(listedPeriodIds(['year' => $year]))->toBe([$lama->id])
        ->and(listedPeriodIds(['search' => 'pengabdian']))->toBe([$baru->id]);
});

it('pengajuan masuk hanya menghitung induk yang terkirim', function () {
    $period = periodWithDates(['+1 month']);
    FormSubmission::factory()->create(['submission_period_id' => $period->id]);
    $induk = FormSubmission::factory()->count(2)->create(['submission_period_id' => $period->id, 'is_submitted' => true]);
    FormSubmission::factory()->create([
        'submission_period_id' => $period->id,
        'is_submitted' => true,
        'parent_submission_id' => $induk->first()->id,
    ]);

    $this->actingAs(periodManager())
        ->get(route('admin.submission-periods.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('periods.data.0.submittedCount', 2)
            ->where('periods.data.0.draftOwnerCount', 1));
});

it('baris membawa penghalang hapus dan kunci tanggal', function () {
    $terpakai = periodWithDates(['+1 month']);
    FormPhaseDetail::factory()->create(['submission_date_id' => $terpakai->submissionDates()->firstOrFail()->id]);
    $kosong = periodWithDates(['+1 month']);

    // Factory FormPhaseDetail membuat periodenya sendiri, jadi baris dicari lewat id.
    $rows = [];
    $this->actingAs(periodManager())
        ->get(route('admin.submission-periods.index'))
        ->assertInertia(function (AssertableInertia $page) use (&$rows) {
            $rows = array_column($page->toArray()['props']['periods']['data'], null, 'id');
        });

    expect($rows[$kosong->id]['deleteBlockers'])->toBe([])
        ->and($rows[$terpakai->id]['dates'][0]['phaseDetailCount'])->toBe(1)
        ->and($rows[$terpakai->id]['deleteBlockers'])->toBe([['label' => 'detail tahap yang memakai tanggalnya', 'count' => 1]]);
});

it('closable mengikuti Close Early', function () {
    expect(PeriodStatus::Active->closable())->toBeTrue()
        ->and(PeriodStatus::NoDates->closable())->toBeTrue()
        ->and(PeriodStatus::Expired->closable())->toBeFalse()
        ->and(PeriodStatus::ForceClosed->closable())->toBeFalse();
});

it('tahap nonaktif yang sudah melekat tetap ditawarkan', function () {
    $aktif = FormPhase::create(['title' => 'Aktif', 'is_active' => true]);
    $nonaktif = FormPhase::create(['title' => 'Lama', 'is_active' => false]);
    FormPhase::create(['title' => 'Yatim', 'is_active' => false]);
    periodWithDates(['+1 month'])->submissionPeriodPhases()->create(['form_phase_id' => $nonaktif->id]);

    $this->actingAs(periodManager())
        ->get(route('admin.submission-periods.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('phaseOptions', [
            ['value' => (string) $aktif->id, 'label' => 'Aktif'],
            ['value' => (string) $nonaktif->id, 'label' => 'Lama (nonaktif)'],
        ]));
});
