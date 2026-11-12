<?php

// K9b — "Hubungkan ke Periode" pada pustaka fase: penautan longgar, beraudit, dijaga periods.manage.

use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->form = Form::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function linkAuditCount(): int
{
    return Activity::inLog(AuditTrail::LOG_NAME)->where('description', 'Tahap formulir dihubungkan ke periode')->count();
}

function linkPhase(): FormPhase
{
    return FormPhase::create(['title' => 'Tahap Uji', 'is_active' => true]);
}

/** Detail di tahap $phase yang aturan aksesnya milik $form; tautan ke periode dibuat tiap test. */
function linkDetail(Form $form, FormPhase $phase): FormPhaseDetail
{
    return formStepIn($form, $phase);
}

function linkPeriodNamed(string $name, bool $forceClosed = false): SubmissionPeriod
{
    $period = SubmissionPeriod::create(['name' => $name, 'is_force_closed' => $forceClosed]);
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => now()->addMonth(),
        'submission_period_id' => $period->id,
    ]);

    return $period;
}

/** Label periode yang ditawarkan kartu $phase, dibatasi pada periode buatan test ini (factory detail membawa periode sendiri). */
function offeredLabels(FormPhase $phase): array
{
    $cards = test()->get(route('admin.phases.index'))->viewData('page')['props']['phases'];
    $card = collect($cards)->firstWhere('id', $phase->id);

    return array_values(array_filter(
        array_column($card['linkablePeriods'], 'label'),
        fn (string $label): bool => in_array($label, ['P1', 'P2', 'Alfa', 'Zeta', 'Ditutup', 'Lewat'], true),
    ));
}

function linkUrl(FormPhase $phase): string
{
    return route('admin.phases.periods.store', $phase);
}

/** Kartu $phase di pustaka. */
function linkCard(FormPhase $phase): array
{
    return collect(test()->get(route('admin.phases.index'))->viewData('page')['props']['phases'])
        ->firstWhere('id', $phase->id);
}

it('menautkan tahap ke periode terbuka mengaudit', function () {
    $phase = linkPhase();
    linkPeriodNamed('P1');
    linkDetail($this->form, $phase);
    // BR-FE-26 (ADR 0019) — P2 belum punya Batas Submit yang dibaca langkah tahap ini.
    $p2 = SubmissionPeriod::create(['name' => 'P2']);

    $this->actingAs($this->admin)
        ->post(linkUrl($phase), ['submission_period_id' => $p2->id])
        ->assertRedirect()
        ->assertSessionHas('success', 'Tahap dihubungkan ke periode P2. Periode P2 belum punya 1 tanggal yang dibutuhkan tahap ini.');

    expect(SubmissionPeriodPhase::where(['form_phase_id' => $phase->id, 'submission_period_id' => $p2->id])->exists())->toBeTrue()
        ->and(linkAuditCount())->toBe(1);
});

it('pesan biasa bila kalender periode sudah memuat tanggal yang dibutuhkan', function () {
    $phase = linkPhase();
    $p1 = linkPeriodNamed('P1');
    linkDetail($this->form, $phase);

    $this->actingAs($this->admin)
        ->post(linkUrl($phase), ['submission_period_id' => $p1->id])
        ->assertSessionHas('success', 'Tahap dihubungkan ke periode P1.');
});

it('periode yang ditutup paksa atau sudah tertaut ditolak dan tidak ditawarkan', function () {
    $phase = linkPhase();
    $p1 = linkPeriodNamed('P1');
    linkDetail($this->form, $phase);
    SubmissionPeriodPhase::create(['submission_period_id' => $p1->id, 'form_phase_id' => $phase->id]);
    $closed = linkPeriodNamed('Ditutup', forceClosed: true);
    $over = linkPeriodNamed('Lewat');
    $over->submissionDates()->update(['datetime' => now()->subDay()]);

    foreach ([$p1, $closed, $over] as $period) {
        $this->actingAs($this->admin)
            ->post(linkUrl($phase), ['submission_period_id' => $period->id])
            ->assertSessionHasErrors('submission_period_id');
    }

    expect(SubmissionPeriodPhase::where('form_phase_id', $phase->id)->count())->toBe(1)
        ->and(linkAuditCount())->toBe(0);

    expect(offeredLabels($phase))->toBe([]);
});

it('periode yang belum tertaut ditawarkan terurut nama', function () {
    $phase = linkPhase();
    linkPeriodNamed('Zeta');
    linkDetail($this->form, $phase);
    linkPeriodNamed('Alfa');

    $this->actingAs($this->admin);

    expect(offeredLabels($phase))->toBe(['Alfa', 'Zeta']);
});

it('kartu menyebut tanggal yang belum ada di kalender periode tertaut', function () {
    $phase = linkPhase();
    $p1 = linkPeriodNamed('P1');
    $p2 = SubmissionPeriod::create(['name' => 'P2']);
    linkDetail($this->form, $phase);
    linkDetail($this->form, $phase);
    SubmissionPeriodPhase::create(['submission_period_id' => $p1->id, 'form_phase_id' => $phase->id]);
    SubmissionPeriodPhase::create(['submission_period_id' => $p2->id, 'form_phase_id' => $phase->id]);

    // Dua langkah membaca satu Batas Submit: satu tanggal yang kurang, bukan dua (FE-WINDOW-04).
    $this->actingAs($this->admin);
    expect(linkCard($phase)['deadlineGaps'])->toBe([['period' => 'P2', 'count' => 1]]);

    SubmissionDate::create(['type' => DeadlineType::Submit, 'datetime' => now()->addMonth(), 'submission_period_id' => $p2->id]);

    expect(linkCard($phase)['deadlineGaps'])->toBe([]);
});

it('kartu tidak memperingatkan periode yang sudah tutup sepenuhnya', function () {
    $phase = linkPhase();
    $p1 = linkPeriodNamed('P1');
    $detail = linkDetail($this->form, $phase);
    // Langkah Monev I membaca tanggal yang tidak pernah diisi di P1, tetapi P1 sudah lewat.
    restep($detail, ['deadline_cycle' => 1]);
    $p1->submissionDates()->update(['datetime' => now()->subDay()]);
    SubmissionPeriodPhase::create(['submission_period_id' => $p1->id, 'form_phase_id' => $phase->id]);

    $this->actingAs($this->admin);
    expect(linkCard($phase)['deadlineGaps'])->toBe([]);
});

it('wewenang: operator 403 (schemes.manage), pengguna schemes.manage tanpa periods.manage 403', function () {
    $phase = linkPhase();
    linkPeriodNamed('P1');
    linkDetail($this->form, $phase);
    $p2 = linkPeriodNamed('P2');
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $schemesOnly = User::factory()->create();
    $schemesOnly->givePermissionTo('schemes.manage');

    foreach ([$operator, $schemesOnly] as $actor) {
        $this->actingAs($actor)
            ->post(linkUrl($phase), ['submission_period_id' => $p2->id])
            ->assertForbidden();
    }

    expect(SubmissionPeriodPhase::where('form_phase_id', $phase->id)->exists())->toBeFalse();
});
