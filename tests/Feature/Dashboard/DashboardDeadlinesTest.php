<?php

// tests/Feature/Dashboard/DashboardDeadlinesTest.php
//
// "Deadline Mendekat" — satu daftar gabungan, urut tenggat, maks 5 (W45).
// Periode terbuka dibaca lewat SubmissionPeriodGate (BR-FE-01), bukan
// predikat baru: periode yang tertutup paksa tidak menagih apa pun.

use App\Data\View\Dashboard\DeadlineData;
use App\Enums\DeadlineType;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Queries\Dashboard\DashboardDeadlines;
use Spatie\Permission\Models\Permission;

function ddPeriod(string $name, string $closesIn, bool $forceClosed = false): SubmissionPeriod
{
    $period = SubmissionPeriod::factory()->create(['name' => $name, 'is_force_closed' => $forceClosed]);
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => now()->modify($closesIn),
        'submission_period_id' => $period->id,
    ]);

    return $period;
}

function ddManager(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('periods.manage', 'web'));

    return $user;
}

/** @return list<string> */
function ddTitles(array $deadlines): array
{
    return array_map(fn (DeadlineData $d) => $d->title, $deadlines);
}

it('periods.manage melihat penutupan periode terbuka, urut naik', function () {
    $this->freezeTime();
    ddPeriod('DIPA 2027', '+9 days');
    ddPeriod('DIPA 2026', '+3 days');

    $deadlines = DashboardDeadlines::run(ddManager(), []);

    expect(ddTitles($deadlines))->toBe(['Tutup periode DIPA 2026', 'Tutup periode DIPA 2027'])
        ->and($deadlines[0]->daysLeft)->toBe(3)
        ->and($deadlines[0]->urgent)->toBeTrue()
        ->and($deadlines[1]->urgent)->toBeFalse()
        ->and($deadlines[0]->href)->toBe(route('admin.submission-periods.index'));
});

it('periode tertutup tidak muncul', function () {
    ddPeriod('Lewat', '-1 day');
    ddPeriod('Ditutup Paksa', '+2 days', forceClosed: true);

    expect(DashboardDeadlines::run(ddManager(), []))->toBe([]);
});

it('maksimal lima, digabung dengan deadline reviewer', function () {
    $this->freezeTime();
    foreach (range(1, 5) as $i) {
        ddPeriod("P{$i}", "+{$i} days");
    }
    $reviewer = [DeadlineData::until('Evaluasi: Biochar', now()->addHours(2), null)];

    $deadlines = DashboardDeadlines::run(ddManager(), $reviewer);

    expect($deadlines)->toHaveCount(5)
        ->and($deadlines[0]->title)->toBe('Evaluasi: Biochar')
        ->and($deadlines[4]->title)->toBe('Tutup periode P4');
});

it('peneliti melihat periode yang bisa ia ajukan', function () {
    $this->freezeTime();
    $fx = wizardFixture();
    SubmissionPeriodPhase::firstOrCreate(['submission_period_id' => $fx['period']->id, 'form_phase_id' => $fx['phase']->id]);

    $deadlines = DashboardDeadlines::run($fx['user'], []);

    expect(ddTitles($deadlines))->toBe(["Periode {$fx['period']->name} ditutup"])
        ->and($deadlines[0]->href)->toBe(route('user.submissions.create'));
});

it('pemegang dua permission tidak melihat periode yang sama dua kali', function () {
    $fx = wizardFixture();
    SubmissionPeriodPhase::firstOrCreate(['submission_period_id' => $fx['period']->id, 'form_phase_id' => $fx['phase']->id]);
    $fx['user']->givePermissionTo(Permission::findOrCreate('periods.manage', 'web'));

    expect(DashboardDeadlines::run($fx['user'], []))->toHaveCount(1);
});

it('tanpa permission dan tanpa deadline reviewer daftar kosong', function () {
    ddPeriod('DIPA', '+3 days');

    expect(DashboardDeadlines::run(User::factory()->create(), []))->toBe([]);
});
