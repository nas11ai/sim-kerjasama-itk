<?php

// ADMIN-08-08 — tab Phases: tahap yang memakai Form ini (W92), hitungan per kartu, wewenang `schemes.manage` (W88).

use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->form = Form::factory()->create();
});

function phaseTabActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Detail baru yang aturan aksesnya milik $form, di tahap $phase (atau tahap baru). */
function phaseTabDetail(Form $form, ?FormPhase $phase = null): FormPhaseDetail
{
    $detail = FormPhaseDetail::factory()->create();
    $detail->formAccessControl?->update(['form_id' => $form->id]);
    if ($phase !== null) {
        $detail->update(['form_phase_id' => $phase->id]);
    }

    return $detail;
}

it('kontrak tab Phases', function () {
    phaseTabDetail($this->form);

    $this->actingAs(phaseTabActor())
        ->get(route('admin.forms.phases.index', $this->form))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/FormEditPage')
            ->where('tab', 'phases')
            ->where('form', ['id' => $this->form->id, 'title' => $this->form->title, 'isActive' => $this->form->is_active])
            ->has('phases.0', fn (AssertableInertia $card) => $card
                ->hasAll(['id', 'title', 'description', 'isActive', 'detailCount', 'periodNames', 'submissionCount', 'linkablePeriods', 'deadlineGaps']))
            ->has('can.linkPeriod'));
});

it('hanya tahap yang detailnya memakai aturan akses Form ini (W92)', function () {
    $other = Form::factory()->create();
    $onlyA = phaseTabDetail($this->form)->formPhase;
    $onlyB = phaseTabDetail($other)->formPhase;
    $both = phaseTabDetail($this->form)->formPhase;
    phaseTabDetail($other, $both);

    $titles = fn (Form $form) => collect(
        test()->actingAs(phaseTabActor())->get(route('admin.forms.phases.index', $form))->viewData('page')['props']['phases']
    )->pluck('id')->all();

    expect($titles($this->form))->toBe([$onlyA->id, $both->id])
        ->and($titles($other))->toBe([$onlyB->id, $both->id]);
});

it('menghitung detail, periode, dan pengajuan per tahap', function () {
    $first = phaseTabDetail($this->form);
    $phase = $first->formPhase;
    $second = phaseTabDetail($this->form, $phase);
    $period = SubmissionPeriod::create(['name' => 'Penelitian DIPA 2026']);
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $phase->id]);
    FormSubmission::factory()->count(2)->create(['form_phase_detail_id' => $first->id]);
    FormSubmission::factory()->create(['form_phase_detail_id' => $second->id]);
    $bare = phaseTabDetail($this->form)->formPhase;

    $this->actingAs(phaseTabActor())
        ->get(route('admin.forms.phases.index', $this->form))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('phases.0.id', $phase->id)
            ->where('phases.0.detailCount', 2)
            ->where('phases.0.periodNames', ['Penelitian DIPA 2026'])
            ->where('phases.0.submissionCount', 3)
            ->where('phases.0.linkablePeriods', [])
            ->where('phases.0.deadlineGaps', [])
            ->where('phases.1.id', $bare->id)
            ->where('phases.1.periodNames', [])
            ->where('phases.1.submissionCount', 0));
});

it('nama periode terurut dan tahap nonaktif tetap terdaftar', function () {
    $phase = phaseTabDetail($this->form)->formPhase;
    $phase->update(['is_active' => false]);
    foreach (['Zeta', 'Alfa'] as $name) {
        $period = SubmissionPeriod::create(['name' => $name]);
        SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $phase->id]);
    }

    $this->actingAs(phaseTabActor())
        ->get(route('admin.forms.phases.index', $this->form))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('phases.0.isActive', false)
            ->where('phases.0.periodNames', ['Alfa', 'Zeta']));
});

it('can.linkPeriod mengikuti periods.manage', function () {
    $this->actingAs(phaseTabActor())
        ->get(route('admin.forms.phases.index', $this->form))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.linkPeriod', true));

    $schemesOnly = User::factory()->create();
    $schemesOnly->givePermissionTo('schemes.manage');

    $this->actingAs($schemesOnly)
        ->get(route('admin.forms.phases.index', $this->form))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.linkPeriod', false));
});

it('wewenang: admin 200, operator 403, tamu ke login', function () {
    $url = route('admin.forms.phases.index', $this->form);

    $this->actingAs(phaseTabActor())->get($url)->assertOk();
    $this->actingAs(phaseTabActor('operator'))->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
});

it('tidak lagi mengekspos endpoint JSON akses form yang tak dipakai', function () {
    expect(Route::has('admin.api.form-access-controls'))->toBeFalse();
});

it('daftar tahap global, hapus massal, dan redirect lama sudah dicabut (W100)', function () {
    expect(Route::has('admin.form-phases.index'))->toBeFalse()
        ->and(Route::has('admin.form-phases.bulk-delete'))->toBeFalse();
});

it('usesForm: tahap memakai Form lewat salah satu detailnya (W92)', function () {
    $phase = phaseTabDetail($this->form)->formPhase;

    expect($phase->usesForm($this->form))->toBeTrue()
        ->and($phase->usesForm(Form::factory()->create()))->toBeFalse();
});

it('wewenang aktif dan hapus: operator 403', function () {
    $phase = phaseTabDetail($this->form)->formPhase;

    $this->actingAs(phaseTabActor('operator'))
        ->patch(route('admin.forms.phases.active', [$this->form, $phase]), ['active' => false])
        ->assertForbidden();
    $this->delete(route('admin.forms.phases.destroy', [$this->form, $phase]))->assertForbidden();

    expect($phase->fresh()?->is_active)->toBeTrue();
});

it('tahap milik Form lain lewat URL: 404 dan tidak berubah', function () {
    $other = Form::factory()->create();
    $phase = phaseTabDetail($other)->formPhase;

    $this->actingAs(phaseTabActor())
        ->patch(route('admin.forms.phases.active', [$this->form, $phase]), ['active' => false])
        ->assertNotFound();
    $this->delete(route('admin.forms.phases.destroy', [$this->form, $phase]))->assertNotFound();

    expect($phase->fresh()?->is_active)->toBeTrue()
        ->and(FormPhase::query()->whereKey($phase->id)->exists())->toBeTrue();
});
