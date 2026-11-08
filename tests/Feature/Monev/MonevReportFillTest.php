<?php

// tests/Feature/Monev/MonevReportFillTest.php
//
// RESEARCHER-05-02 — jalur tulis laporan monev sendiri (W121): sebelum M1 simpan dan submit
// laporan anak selalu 409 karena resolver wizard menyaring parent_submission_id IS NULL.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Actions\Override\GrantPhaseOverride;
use App\Data\Request\PhaseOverrideInput;
use App\Enums\MonevCycleState;
use App\Events\ProposalSubmitted;
use App\Models\AuditEntry;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Monev\MonevCycles;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use App\States\Submission\Withdrawn;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->fx = monevFormFixture();
    $this->fx['owner']->assignRole('researcher');
    $this->report = StartChildSubmission::run($this->fx['parent'], $this->fx['monev1'], $this->fx['owner']);
});

/** @return array{last_updated_at: string|null, responses: list<array{form_field_id: int, value: string|null}>} */
function monevReportPayload(FormSubmission $report, int $fieldId, ?string $value): array
{
    return [
        'last_updated_at' => $report->fresh()->updated_at?->toISOString(),
        'responses' => [['form_field_id' => $fieldId, 'value' => $value]],
    ];
}

function monevReportMember(FormSubmission $parent): User
{
    $user = User::factory()->create();
    $user->assignRole('researcher');
    ResearchMember::factory()->coInvestigator()->create([
        'form_submission_id' => $parent->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
    ]);

    return $user;
}

function monevGrantOverride(FormSubmission $report, FormPhaseDetail $detail): void
{
    $operator = verifiedResearcher();
    $operator->givePermissionTo('submissions.view-all');

    GrantPhaseOverride::run(
        $report,
        $detail->fresh(),
        $operator,
        new PhaseOverrideInput('Kendala jaringan saat batas monev berjalan', now()->addWeek()->toDateTimeString()),
    );
}

it('kontrak halaman isi laporan', function () {
    $this->actingAs($this->fx['owner'])->get(route('monev.reports.edit', $this->report))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Monev/ReportFormPage')
            ->has('report', fn (AssertableInertia $report) => $report
                ->where('id', $this->report->id)
                ->where('parentId', $this->fx['parent']->id)
                ->where('cycleName', 'Monev I')
                ->hasAll(['title', 'scheme', 'deadline', 'lastUpdatedAt']))
            ->where('cycle.state', 'open')
            ->has('fields', 2)
            ->has('fields.0', fn (AssertableInertia $field) => $field
                ->where('label', 'Ringkasan Kemajuan')
                ->where('required', true)
                ->hasAll(['id', 'type', 'value', 'options', 'fileName', 'downloadUrl']))
            ->has('phaseAccess', fn (AssertableInertia $access) => $access
                ->where('is_open', true)
                ->etc())
            ->where('can', ['save' => true, 'submit' => true]));
});

it('simpan draft menulis jawaban dan mengaudit', function () {
    $this->actingAs($this->fx['owner'])
        ->from(route('monev.reports.edit', $this->report))
        ->post(route('monev.reports.save', $this->report), monevReportPayload($this->report, $this->fx['summary']->id, 'Capaian 45%'))
        ->assertRedirect(route('monev.reports.edit', $this->report))
        ->assertSessionHasNoErrors();

    expect($this->report->formFieldResponses()->where('form_field_id', $this->fx['summary']->id)->value('value'))
        ->toBe('Capaian 45%')
        ->and(AuditEntry::query()->where('subject_id', $this->report->id)->where('description', 'Draft laporan monev disimpan')->exists())
        ->toBeTrue();
});

it('simpan draft dengan cap waktu basi ditolak 409', function () {
    $payload = [...monevReportPayload($this->report, $this->fx['summary']->id, 'x'), 'last_updated_at' => '2020-01-01T00:00:00.000000Z'];

    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.save', $this->report), $payload)
        ->assertStatus(409);
});

it('[FE-LOCK-02] simpanan laporan kedua dari cap yang sama ditolak 409', function () {
    // W161 — simpanan pertama sendiri yang harus menggeser cap kunci.
    $cap = monevReportPayload($this->report, $this->fx['summary']->id, 'Versi tab A');
    $this->travel(1)->seconds();

    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.save', $this->report), $cap)
        ->assertSessionHasNoErrors();
    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.save', $this->report), [...$cap, 'responses' => [['form_field_id' => $this->fx['summary']->id, 'value' => 'Versi tab B']]])
        ->assertStatus(409);

    expect($this->report->formFieldResponses()->where('form_field_id', $this->fx['summary']->id)->value('value'))
        ->toBe('Versi tab A');
});

it('simpanan laporan tanpa perubahan jawaban tidak menggeser cap kunci', function () {
    $payload = monevReportPayload($this->report, $this->fx['summary']->id, 'Capaian 45%');
    $this->actingAs($this->fx['owner'])->post(route('monev.reports.save', $this->report), $payload);
    $cap = $this->report->fresh()->updated_at?->toISOString();
    $this->travel(1)->seconds();

    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.save', $this->report), [...$payload, 'last_updated_at' => $cap])
        ->assertSessionHasNoErrors();

    expect($this->report->fresh()->updated_at?->toISOString())->toBe($cap);
});

it('submit laporan lengkap memindah ke SUBMITTED tanpa ProposalSubmitted', function () {
    Event::fake([ProposalSubmitted::class]);

    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.submit', $this->report), monevReportPayload($this->report, $this->fx['summary']->id, 'Capaian 45%'))
        ->assertRedirect(route('monev.reports.show', $this->report))
        ->assertSessionHasNoErrors();

    $report = $this->report->fresh();
    expect($report->is_submitted)->toBeTrue()
        ->and($report->status)->toBeInstanceOf(Submitted::class)
        ->and(AuditEntry::query()->where('subject_id', $report->id)->where('description', MonevCycles::REPORT_SUBMITTED_AUDIT)->exists())
        ->toBeTrue();
    Event::assertNotDispatched(ProposalSubmitted::class);
});

it('submit tanpa field wajib ditolak di field itu', function () {
    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.submit', $this->report), monevReportPayload($this->report, $this->fx['summary']->id, null))
        ->assertSessionHasErrors('field_'.$this->fx['summary']->id);

    expect($this->report->fresh()->is_submitted)->toBeFalse();
});

it('[MON-CYCLE-04] submit Monev I tanpa review membuka Monev II', function () {
    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.submit', $this->report), monevReportPayload($this->report, $this->fx['summary']->id, 'Capaian 45%'));

    $cycles = MonevCycles::run($this->fx['parent']->fresh());

    expect($cycles[0]->state)->toBe(MonevCycleState::Done)
        ->and($cycles[0]->submittedAt)->not->toBeNull()
        ->and($cycles[1]->state)->toBe(MonevCycleState::Open);
});

it('[MON-CYCLE-08] simpan lewat batas tanpa override 403', function () {
    $this->travelTo(now()->addMonths(2));

    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.save', $this->report), monevReportPayload($this->report, $this->fx['summary']->id, 'x'))
        ->assertForbidden();
});

it('[MON-CYCLE-09] simpan dengan override yang berlaku berhasil', function () {
    $this->travelTo(now()->addMonths(2));
    monevGrantOverride($this->report, $this->fx['monev1']);

    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.save', $this->report), monevReportPayload($this->report, $this->fx['summary']->id, 'x'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('[MON-CYCLE-13] draft laporan tidak bisa disubmit setelah penarikan', function () {
    $this->fx['parent']->status->transitionTo(Withdrawn::class);

    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.submit', $this->report), monevReportPayload($this->report, $this->fx['summary']->id, 'Capaian 45%'))
        ->assertSessionHasErrors('parent_submission_id');

    $report = $this->report->fresh();
    expect($report->is_submitted)->toBeFalse()
        ->and($report->status)->toBeInstanceOf(Draft::class);
});

it('simpan draft induk tarikan ditolak', function () {
    $this->fx['parent']->status->transitionTo(Withdrawn::class);

    $this->actingAs($this->fx['owner'])
        ->post(route('monev.reports.save', $this->report), monevReportPayload($this->report, $this->fx['summary']->id, 'x'))
        ->assertSessionHasErrors(['parent_submission_id' => 'Pengajuan ini sudah ditarik. Siklus monevnya dibekukan dan tidak menerima laporan baru.']);

    expect($this->report->formFieldResponses()->count())->toBe(0);
});

it('[MON-CYCLE-14] anggota tim tidak bisa mengisi maupun mengirim laporan', function () {
    $ani = monevReportMember($this->fx['parent']);
    $payload = monevReportPayload($this->report, $this->fx['summary']->id, 'x');

    $this->actingAs($ani)->post(route('monev.reports.save', $this->report), $payload)->assertForbidden();
    $this->actingAs($ani)->post(route('monev.reports.submit', $this->report), $payload)->assertForbidden();
    $this->actingAs($ani)->get(route('monev.reports.edit', $this->report))->assertRedirect(route('monev.reports.show', $this->report));
});

it('override terlihat tanpa alasan', function () {
    $this->travelTo(now()->addMonths(2));
    monevGrantOverride($this->report, $this->fx['monev1']);

    $this->actingAs($this->fx['owner'])->get(route('monev.reports.edit', $this->report))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('phaseAccess.is_open', true)
            ->where('phaseAccess.override.status', 'active')
            ->where('phaseAccess.override.reason', null)
            ->where('cycle.state', 'open'));
});

it('SubmitFormSubmission menolak laporan anak', function () {
    try {
        SubmitFormSubmission::run($this->report, $this->fx['owner']);
        $this->fail('Laporan anak lolos jalur pengajuan.');
    } catch (ValidationException $e) {
        expect($e->errors()['form_phase_detail_id'][0])->toBe('Laporan monev dikirim dari halaman laporannya.');
    }

    expect($this->report->fresh()->is_submitted)->toBeFalse();
});
