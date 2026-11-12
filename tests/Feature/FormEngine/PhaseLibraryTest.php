<?php

// ADMIN-08-08 — pustaka fase `/admin/phases`: semua tahap urut judul (R2-W5), tapis `?form=` (langkah
// mana pun memakai Form itu), Form pengajuan per kartu (BR-FE-28), wewenang `schemes.manage` (W88).

use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->form = Form::factory()->create(['title' => 'Pengajuan Penelitian']);
});

function libraryActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Detail baru yang aturan aksesnya milik $form, di tahap $phase (atau tahap baru). */
function libraryDetail(Form $form, ?FormPhase $phase = null, array $attributes = []): FormPhaseDetail
{
    $detail = FormPhaseDetail::factory()->create($attributes);
    $detail->formAccessControl?->update(['form_id' => $form->id]);
    if ($phase !== null) {
        $detail->update(['form_phase_id' => $phase->id]);
    }

    return $detail;
}

function libraryPhase(string $title): FormPhase
{
    return FormPhase::create(['title' => $title, 'is_active' => true]);
}

/** @return list<int> id kartu, dibatasi pada tahap buatan test ini (pabrik langkah membuat tahapnya sendiri). */
function libraryIds(string $url, array $only): array
{
    $cards = test()->get($url)->viewData('page')['props']['phases'];

    return array_values(array_filter(array_column($cards, 'id'), fn (int $id): bool => in_array($id, $only, true)));
}

it('kontrak pustaka fase', function () {
    libraryDetail($this->form);

    $this->actingAs(libraryActor())
        ->get(route('admin.phases.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseIndexPage')
            ->where('filterForm', null)
            ->has('phases.0', fn (AssertableInertia $card) => $card
                ->hasAll(['id', 'title', 'description', 'isActive', 'detailCount', 'periodNames', 'submissionCount', 'linkablePeriods', 'deadlineGaps', 'submissionFormTitle']))
            ->has('can.linkPeriod'));
});

it('lists every phase ordered by title', function () {
    $zeta = libraryPhase('Zeta');
    $alfa = libraryPhase('Alfa');
    $alfaLater = libraryPhase('Alfa');
    libraryDetail($this->form, $zeta);
    libraryDetail(Form::factory()->create(), $alfaLater);

    $this->actingAs(libraryActor());

    // R2-W5 — judul lalu id; tahap dengan Form apa pun (atau tanpa langkah) ikut terdaftar.
    expect(libraryIds(route('admin.phases.index'), [$zeta->id, $alfa->id, $alfaLater->id]))
        ->toBe([$alfa->id, $alfaLater->id, $zeta->id]);
});

it('shows the submission form of each phase', function () {
    $phase = libraryPhase('Fase Penelitian');
    $monev = Form::factory()->create(['title' => 'Laporan Monev']);
    libraryDetail($monev, $phase, ['order' => 2, 'deadline_cycle' => 1]);
    libraryDetail($this->form, $phase);
    $bare = libraryPhase('Fase Tanpa Pengajuan');
    libraryDetail($monev, $bare, ['deadline_cycle' => 1]);

    $cards = collect($this->actingAs(libraryActor())->get(route('admin.phases.index'))
        ->viewData('page')['props']['phases'])->keyBy('id');

    // BR-FE-28 — paling banyak satu Form pengajuan per tahap; tahap lama hasil insert langsung bisa tanpa.
    expect($cards[$phase->id]['submissionFormTitle'])->toBe('Pengajuan Penelitian')
        ->and($cards[$bare->id]['submissionFormTitle'])->toBeNull();
});

it('filters to phases where any step uses the form', function () {
    $monev = Form::factory()->create(['title' => 'Laporan Monev']);
    $withMonev = libraryPhase('Fase A');
    libraryDetail($this->form, $withMonev);
    libraryDetail($monev, $withMonev, ['order' => 2, 'deadline_cycle' => 1]);
    $without = libraryPhase('Fase B');
    libraryDetail($this->form, $without);

    $this->actingAs(libraryActor());
    $url = route('admin.phases.index', ['form' => $monev->id]);

    expect(libraryIds($url, [$withMonev->id, $without->id]))->toBe([$withMonev->id]);
    $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filterForm', ['id' => $monev->id, 'title' => 'Laporan Monev', 'isActive' => $monev->is_active]));
});

it('returns 404 for an unknown form filter', function () {
    $this->actingAs(libraryActor());

    $this->get(route('admin.phases.index', ['form' => 999999]))->assertNotFound();
    $this->get(route('admin.phases.index', ['form' => 'abc']))->assertNotFound();
});

it('menghitung detail, periode, dan pengajuan per tahap', function () {
    // Fase eksplisit: fase bawaan pabrik langkah sudah tertaut periode ujinya sendiri.
    $phase = libraryPhase('Fase Hitung');
    $first = libraryDetail($this->form, $phase);
    $second = libraryDetail($this->form, $phase);
    $period = SubmissionPeriod::create(['name' => 'Penelitian DIPA 2026']);
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $phase->id]);
    FormSubmission::factory()->count(2)->create(['form_phase_detail_id' => $first->id]);
    FormSubmission::factory()->create(['form_phase_detail_id' => $second->id]);
    $bare = libraryPhase('Fase Polos');
    libraryDetail($this->form, $bare);

    $cards = collect($this->actingAs(libraryActor())->get(route('admin.phases.index'))
        ->viewData('page')['props']['phases'])->keyBy('id');

    expect($cards[$phase->id]['detailCount'])->toBe(2)
        ->and($cards[$phase->id]['periodNames'])->toBe(['Penelitian DIPA 2026'])
        ->and($cards[$phase->id]['submissionCount'])->toBe(3)
        ->and(collect($cards[$phase->id]['linkablePeriods'])->doesntContain('label', 'Penelitian DIPA 2026'))->toBeTrue()
        // ADR 0019 — dua langkah membaca satu Batas Submit yang belum ada: satu tanggal kurang.
        ->and($cards[$phase->id]['deadlineGaps'])->toBe([['period' => 'Penelitian DIPA 2026', 'count' => 1]])
        ->and($cards[$bare->id]['periodNames'])->toBe([])
        ->and($cards[$bare->id]['submissionCount'])->toBe(0);
});

it('tidak menambah kueri per kartu', function () {
    $this->actingAs(libraryActor());
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->get(route('admin.phases.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    libraryDetail($this->form, libraryPhase('Fase 1'));
    $count(); // pemanasan: request pertama memuat izin dan sesi
    $one = $count();
    foreach (['Fase 2', 'Fase 3', 'Fase 4'] as $title) {
        libraryDetail(Form::factory()->create(), libraryPhase($title));
    }

    expect($count())->toBe($one);
});

it('nama periode terurut dan tahap nonaktif tetap terdaftar', function () {
    $phase = FormPhase::create(['title' => 'Fase Nonaktif', 'is_active' => false]);
    libraryDetail($this->form, $phase);
    foreach (['Zeta', 'Alfa'] as $name) {
        $period = SubmissionPeriod::create(['name' => $name]);
        SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $phase->id]);
    }

    $card = collect($this->actingAs(libraryActor())->get(route('admin.phases.index'))
        ->viewData('page')['props']['phases'])->firstWhere('id', $phase->id);

    expect($card['isActive'])->toBeFalse()
        ->and($card['periodNames'])->toBe(['Alfa', 'Zeta']);
});

it('hides the link-period action without periods.manage', function () {
    $phase = libraryPhase('Fase Tautan');
    libraryDetail($this->form, $phase);
    $period = SubmissionPeriod::create(['name' => 'P2']);

    $this->actingAs(libraryActor())
        ->get(route('admin.phases.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.linkPeriod', true));

    $schemesOnly = User::factory()->create();
    $schemesOnly->givePermissionTo('schemes.manage');

    $this->actingAs($schemesOnly)
        ->get(route('admin.phases.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.linkPeriod', false));
    $this->post(route('admin.phases.periods.store', $phase), ['submission_period_id' => $period->id])
        ->assertForbidden();

    expect(SubmissionPeriodPhase::where('form_phase_id', $phase->id)->exists())->toBeFalse();
});

it('wewenang: admin 200, operator 403, tamu ke login', function () {
    $url = route('admin.phases.index');

    $this->actingAs(libraryActor())->get($url)->assertOk();
    $this->actingAs(libraryActor('operator'))->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
});

it('tidak lagi mengekspos endpoint JSON akses form yang tak dipakai', function () {
    expect(Route::has('admin.api.form-access-controls'))->toBeFalse();
});

it('route tahap bersarang di Form dan daftar tahap global lama sudah dicabut (W100, R2-W4)', function () {
    $legacy = collect(array_keys(Route::getRoutes()->getRoutesByName()))
        ->filter(fn (string $name): bool => str_starts_with($name, 'admin.form-phases')
            || str_starts_with($name, 'admin.forms.phases'));

    expect($legacy)->toBeEmpty();
});

it('wewenang aktif dan hapus: operator 403', function () {
    $phase = libraryDetail($this->form)->formPhase;

    $this->actingAs(libraryActor('operator'))
        ->patch(route('admin.phases.active', $phase), ['active' => false])
        ->assertForbidden();
    $this->delete(route('admin.phases.destroy', $phase))->assertForbidden();

    expect($phase->fresh()?->is_active)->toBeTrue();
});

it('stays on the filtered library after destroy', function () {
    $phase = libraryPhase('Fase Hapus');
    libraryDetail($this->form, $phase);
    $filtered = route('admin.phases.index', ['form' => $this->form->id]);

    // B5 — hapus kembali ke halaman asal (pustaka bertapis), sama dengan aktif/nonaktif.
    $this->actingAs(libraryActor())
        ->from($filtered)
        ->delete(route('admin.phases.destroy', $phase))
        ->assertSessionHasNoErrors()
        ->assertRedirect($filtered);

    expect(FormPhase::query()->whereKey($phase->id)->exists())->toBeFalse();
});
