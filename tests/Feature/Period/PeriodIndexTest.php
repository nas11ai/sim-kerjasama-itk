<?php

// OPERATOR-09-01 — kontrak daftar periode, filter, dan wewenang `periods.manage` (K1).

use App\Enums\DeadlineType;
use App\Enums\PeriodStatus;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
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
            ...deadlineSlot($i),
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
    $phase = publishedPhase(['title' => 'Proposal', 'is_active' => true]);
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
                    ->where('type', 'submit')
                    ->where('cycle', null)
                    ->where('label', 'Batas Pengiriman')
                    ->where('passed', false)
                    ->where('phaseDetailCount', 0)
                    ->has('id')->has('at')->has('inputValue'))
                ->where('formPhaseIds', [$phase->id])
                ->where('submittedCount', 0)
                ->where('draftOwnerCount', 0)
                ->where('closable', true)
                ->where('deleteBlockers', [])
                // BR-FE-36 — Versi 1 tanpa langkah: tidak ada isu, jadi tidak ada angkatan belum siap.
                ->where('unreadyCohortCount', 0))
            ->where('filters', ['search' => '', 'status' => '', 'year' => ''])
            ->has('statusOptions', 4)
            ->has('yearOptions')
            // BR-FE-25 v2.4 — empat jenis, termasuk Batas Akhir (W139).
            ->has('typeOptions', 4)
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

it('status Dibuka mengikuti Batas Submit siklus utama, bukan tanggal mana pun (K8d)', function () {
    // Slot 0 = Batas Submit, slot 1 = Batas Evaluasi (deadlineSlot).
    $submitLewat = periodWithDates(['-1 day', '+1 month']);
    $tanpaSubmit = SubmissionPeriod::factory()->create();
    $tanpaSubmit->submissionDates()->create(['type' => DeadlineType::Evaluation, 'datetime' => now()->addMonth()]);
    $periods = [$submitLewat, $tanpaSubmit, periodWithDates(['+1 day', '-1 day'])];

    expect(array_map(fn (SubmissionPeriod $p) => SubmissionPeriodGate::displayStatus($p->fresh()), $periods))
        ->toBe(['expired', 'no_dates', 'active']);

    foreach (['expired' => $submitLewat, 'no_dates' => $tanpaSubmit] as $status => $period) {
        expect(listedPeriodIds(['status' => $status]))->toContain($period->id);
    }
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
    // BR-FE-26 (ADR 0019) — tanggal terkunci bila langkah fase tertaut membacanya; penghalang
    // hapus hanya pengajuan.
    $phase = publishedPhase(['title' => 'Tahap Kunci', 'is_active' => true]);
    scheduleStep($phase, needsReview: false);
    $terpakai = periodWithDates(['+1 month']);
    SubmissionPeriodPhase::create(['submission_period_id' => $terpakai->id, 'form_phase_id' => $phase->id]);
    FormSubmission::factory()->create(['submission_period_id' => $terpakai->id]);
    $kosong = periodWithDates(['+1 month']);

    $rows = [];
    $this->actingAs(periodManager())
        ->get(route('admin.submission-periods.index'))
        ->assertInertia(function (AssertableInertia $page) use (&$rows) {
            $rows = array_column($page->toArray()['props']['periods']['data'], null, 'id');
        });

    expect($rows[$kosong->id]['deleteBlockers'])->toBe([])
        ->and($rows[$terpakai->id]['dates'][0]['phaseDetailCount'])->toBe(1)
        ->and($rows[$terpakai->id]['deleteBlockers'])->toBe([['label' => 'pengajuan', 'count' => 1]]);
});

it('closable mengikuti Close Early', function () {
    expect(PeriodStatus::Active->closable())->toBeTrue()
        ->and(PeriodStatus::NoDates->closable())->toBeTrue()
        ->and(PeriodStatus::Expired->closable())->toBeFalse()
        ->and(PeriodStatus::ForceClosed->closable())->toBeFalse();
});

it('tahap nonaktif yang sudah melekat tetap ditawarkan', function () {
    $aktif = publishedPhase(['title' => 'Aktif', 'is_active' => true]);
    $nonaktif = publishedPhase(['title' => 'Lama', 'is_active' => false]);
    FormPhase::create(['title' => 'Yatim', 'is_active' => false]);
    periodWithDates(['+1 month'])->submissionPeriodPhases()->create(['form_phase_id' => $nonaktif->id]);

    $this->actingAs(periodManager())
        ->get(route('admin.submission-periods.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('phaseOptions', [
            ['value' => (string) $aktif->id, 'label' => 'Aktif'],
            ['value' => (string) $nonaktif->id, 'label' => 'Lama (nonaktif)'],
        ]));
});

it('[FE-DELETE-12] hapus periode kosong lewat HTTP', function () {
    $period = periodWithDates(['+1 month']);

    $this->actingAs(periodManager())
        ->delete(route('admin.submission-periods.destroy', $period))
        ->assertRedirect(route('admin.submission-periods.index'));

    expect(SubmissionPeriod::query()->whereKey($period->id)->exists())->toBeFalse();
});

it('[FE-DELETE-10] hapus periode berpengajuan lewat HTTP ditolak', function () {
    $period = periodWithDates(['+1 month']);
    FormSubmission::factory()->create(['submission_period_id' => $period->id]);

    $this->actingAs(periodManager())
        ->delete(route('admin.submission-periods.destroy', $period))
        ->assertSessionHasErrors();

    expect(SubmissionPeriod::query()->whereKey($period->id)->exists())->toBeTrue();
});

it('peneliti tidak bisa menghapus periode', function () {
    $period = periodWithDates([]);

    $this->actingAs(periodManager('researcher'))
        ->delete(route('admin.submission-periods.destroy', $period))
        ->assertForbidden();
});
