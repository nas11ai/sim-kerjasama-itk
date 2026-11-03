<?php

// K9b — "Hubungkan ke Periode" pada tab Phases: penautan longgar, beraudit, dijaga periods.manage.

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
use Inertia\Testing\AssertableInertia;
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

/** Detail di tahap $phase yang aturan aksesnya milik $form; Batas Submit-nya milik $period. */
function linkDetail(Form $form, FormPhase $phase, SubmissionPeriod $period): FormPhaseDetail
{
    $detail = FormPhaseDetail::factory()->create();
    $detail->formAccessControl?->update(['form_id' => $form->id]);
    $date = $period->submissionDates()->firstOrFail();
    $detail->update(['form_phase_id' => $phase->id, 'submission_date_id' => $date->id]);

    return $detail;
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

/** Label periode yang ditawarkan kartu pertama, dibatasi pada periode buatan test ini (factory detail membawa periode sendiri). */
function offeredLabels(Form $form): array
{
    $cards = test()->get(route('admin.forms.phases.index', $form))->viewData('page')['props']['phases'];

    return array_values(array_filter(
        array_column($cards[0]['linkablePeriods'], 'label'),
        fn (string $label): bool => in_array($label, ['P1', 'P2', 'Alfa', 'Zeta', 'Ditutup', 'Lewat'], true),
    ));
}

function linkUrl(Form $form, FormPhase $phase): string
{
    return route('admin.forms.phases.periods.store', [$form, $phase]);
}

it('menautkan tahap ke periode terbuka mengaudit', function () {
    $phase = linkPhase();
    linkDetail($this->form, $phase, linkPeriodNamed('P1'));
    $p2 = linkPeriodNamed('P2');

    $this->actingAs($this->admin)
        ->post(linkUrl($this->form, $phase), ['submission_period_id' => $p2->id])
        ->assertRedirect()
        ->assertSessionHas('success', 'Tahap dihubungkan ke periode P2. Perbarui tenggat detailnya agar terbuka di periode ini.');

    expect(SubmissionPeriodPhase::where(['form_phase_id' => $phase->id, 'submission_period_id' => $p2->id])->exists())->toBeTrue()
        ->and(linkAuditCount())->toBe(1);
});

it('pesan biasa bila semua detail sudah bertenggat periode itu', function () {
    $phase = linkPhase();
    $p1 = linkPeriodNamed('P1');
    linkDetail($this->form, $phase, $p1);

    $this->actingAs($this->admin)
        ->post(linkUrl($this->form, $phase), ['submission_period_id' => $p1->id])
        ->assertSessionHas('success', 'Tahap dihubungkan ke periode P1.');
});

it('periode yang ditutup paksa atau sudah tertaut ditolak dan tidak ditawarkan', function () {
    $phase = linkPhase();
    $p1 = linkPeriodNamed('P1');
    linkDetail($this->form, $phase, $p1);
    SubmissionPeriodPhase::create(['submission_period_id' => $p1->id, 'form_phase_id' => $phase->id]);
    $closed = linkPeriodNamed('Ditutup', forceClosed: true);
    $over = linkPeriodNamed('Lewat');
    $over->submissionDates()->update(['datetime' => now()->subDay()]);

    foreach ([$p1, $closed, $over] as $period) {
        $this->actingAs($this->admin)
            ->post(linkUrl($this->form, $phase), ['submission_period_id' => $period->id])
            ->assertSessionHasErrors('submission_period_id');
    }

    expect(SubmissionPeriodPhase::where('form_phase_id', $phase->id)->count())->toBe(1)
        ->and(linkAuditCount())->toBe(0);

    expect(offeredLabels($this->form))->toBe([]);
});

it('periode yang belum tertaut ditawarkan terurut nama', function () {
    $phase = linkPhase();
    linkDetail($this->form, $phase, linkPeriodNamed('Zeta'));
    linkPeriodNamed('Alfa');

    $this->actingAs($this->admin);

    expect(offeredLabels($this->form))->toBe(['Alfa', 'Zeta']);
});

it('kartu menyebut detail yang masih bertenggat periode lain', function () {
    $phase = linkPhase();
    $p1 = linkPeriodNamed('P1');
    $p2 = linkPeriodNamed('P2');
    $first = linkDetail($this->form, $phase, $p1);
    $second = linkDetail($this->form, $phase, $p1);
    SubmissionPeriodPhase::create(['submission_period_id' => $p1->id, 'form_phase_id' => $phase->id]);
    SubmissionPeriodPhase::create(['submission_period_id' => $p2->id, 'form_phase_id' => $phase->id]);
    $url = route('admin.forms.phases.index', $this->form);

    $this->actingAs($this->admin)->get($url)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('phases.0.deadlineGaps', [['period' => 'P2', 'count' => 2]]));

    $p2Date = $p2->submissionDates()->firstOrFail();
    FormPhaseDetail::whereIn('id', [$first->id, $second->id])->update(['submission_date_id' => $p2Date->id]);

    // Tenggat dipindah ke P2: kini P1 yang kehilangan pemilik tenggatnya, P2 bersih.
    $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('phases.0.deadlineGaps', [['period' => 'P1', 'count' => 2]]));
});

it('wewenang: operator 403 (schemes.manage), pengguna schemes.manage tanpa periods.manage 403', function () {
    $phase = linkPhase();
    linkDetail($this->form, $phase, linkPeriodNamed('P1'));
    $p2 = linkPeriodNamed('P2');
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $schemesOnly = User::factory()->create();
    $schemesOnly->givePermissionTo('schemes.manage');

    foreach ([$operator, $schemesOnly] as $actor) {
        $this->actingAs($actor)
            ->post(linkUrl($this->form, $phase), ['submission_period_id' => $p2->id])
            ->assertForbidden();
    }

    expect(SubmissionPeriodPhase::where('form_phase_id', $phase->id)->exists())->toBeFalse();
});

it('tahap milik Form lain lewat URL: 404', function () {
    $other = Form::factory()->create();
    $phase = linkPhase();
    linkDetail($other, $phase, linkPeriodNamed('P1'));
    $p2 = linkPeriodNamed('P2');

    $this->actingAs($this->admin)
        ->post(linkUrl($this->form, $phase), ['submission_period_id' => $p2->id])
        ->assertNotFound();
});
