<?php

// BR-FE-29 (K-2) — satu periode paling banyak punya satu tahap per Form pengajuan. Peneliti memilih
// Form, bukan tahap, jadi tahap kedua dengan Form yang sama tidak pernah terjangkau.

use App\Actions\Form\PublishPhaseVersion;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseStructureInput;
use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->proposal = Form::factory()->create(['title' => 'Proposal Penelitian']);
    $this->dipa = onePhaseFor('Penelitian DIPA', $this->proposal);
    $this->period = SubmissionPeriod::create(['name' => '2026 Gelombang 1']);
    SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => now()->addMonth(),
        'submission_period_id' => $this->period->id,
    ]);
    SubmissionPeriodPhase::create(['submission_period_id' => $this->period->id, 'form_phase_id' => $this->dipa->id]);
});

/** Tahap dengan satu langkah Pengajuan pada $form, ditambah langkah lain bila diminta. */
function onePhaseFor(string $title, Form $form, array $attributes = []): FormPhase
{
    $phase = FormPhase::create(['title' => $title, 'is_active' => true]);
    formStepIn($form, $phase, $attributes);

    return $phase;
}

function onePhaseMessage(): string
{
    return 'Tahap Penelitian DIPA dan Penelitian DIPA Salinan sama-sama memakai Formulir pengajuan Proposal Penelitian. Satu periode hanya boleh punya satu tahap per Formulir pengajuan.';
}

function onePhaseLinks(SubmissionPeriod $period): array
{
    return SubmissionPeriodPhase::query()->where('submission_period_id', $period->id)
        ->orderBy('form_phase_id')->pluck('form_phase_id')->all();
}

it('[FE-PHASE-07] rejects linking a second phase with the same submission form from the phase card', function () {
    $copy = onePhaseFor('Penelitian DIPA Salinan', $this->proposal);

    $this->actingAs($this->admin)
        ->post(route('admin.phases.periods.store', $copy), ['submission_period_id' => $this->period->id])
        ->assertSessionHasErrors(['submission_period_id' => onePhaseMessage()]);

    expect(onePhaseLinks($this->period))->toBe([$this->dipa->id])
        ->and(Activity::inLog(AuditTrail::LOG_NAME)->where('description', 'Tahap formulir dihubungkan ke periode')->count())->toBe(0);
});

it('[FE-PHASE-07] still links a phase with another submission form or with no submission step', function () {
    $pengmas = onePhaseFor('Pengabdian DIPA', Form::factory()->create(['title' => 'Proposal Pengmas']));
    // Tahap lama hasil insert langsung tanpa langkah Pengajuan tidak bentrok dengan siapa pun.
    $monevOnly = onePhaseFor('Monev Saja', $this->proposal, ['deadline_cycle' => 1]);

    foreach ([$pengmas, $monevOnly] as $phase) {
        $this->actingAs($this->admin)
            ->post(route('admin.phases.periods.store', $phase), ['submission_period_id' => $this->period->id])
            ->assertSessionHasNoErrors();
    }

    expect(onePhaseLinks($this->period))->toBe([$this->dipa->id, $pengmas->id, $monevOnly->id]);
});

it('[FE-PHASE-08] rejects a period screen save that lists two phases with the same submission form', function () {
    $copy = onePhaseFor('Penelitian DIPA Salinan', $this->proposal);
    $payload = fn (string $name) => [
        'name' => $name,
        'submission_dates' => [['type' => 'submit', 'date' => '2030-01-01']],
        'form_phase_ids' => [$this->dipa->id, $copy->id],
    ];

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload('2026 Gelombang 2'))
        ->assertSessionHasErrors(['form_phase_ids' => onePhaseMessage()]);
    $this->put(route('admin.submission-periods.update', $this->period), $payload('2026 Gelombang 1'))
        ->assertSessionHasErrors(['form_phase_ids' => onePhaseMessage()]);

    expect(SubmissionPeriod::query()->where('name', '2026 Gelombang 2')->exists())->toBeFalse()
        ->and(onePhaseLinks($this->period))->toBe([$this->dipa->id]);
});

it('[FE-PHASE-09] does not offer a period that already has a phase for the same submission form', function () {
    $copy = onePhaseFor('Penelitian DIPA Salinan', $this->proposal);
    $pengmas = onePhaseFor('Pengabdian DIPA', Form::factory()->create(['title' => 'Proposal Pengmas']));

    $cards = collect($this->actingAs($this->admin)->get(route('admin.phases.index'))
        ->viewData('page')['props']['phases'])->keyBy('id');
    $offers = fn (FormPhase $phase): bool => collect($cards[$phase->id]['linkablePeriods'])
        ->contains('label', '2026 Gelombang 1');

    expect($offers($copy))->toBeFalse()
        ->and($offers($pengmas))->toBeTrue();
});

it('[FE-PHASE-10] refuses to publish a version that gives a linked phase the submission form of another phase in the period', function () {
    $pengmas = onePhaseFor('Pengabdian DIPA', Form::factory()->create(['title' => 'Proposal Pengmas']));
    SubmissionPeriodPhase::create(['submission_period_id' => $this->period->id, 'form_phase_id' => $pengmas->id]);
    $proposalStep = $this->dipa->currentDetails()->sole();
    $structure = new PhaseStructureInput([new FormPhaseDetailInput(
        id: null,
        formAccessControlId: $proposalStep->form_access_control_id,
        phaseTypeId: $proposalStep->phase_type_id,
        needsReview: false,
        deadlineType: DeadlineType::Submit,
        deadlineCycle: null,
    )]);

    expect(fn () => PublishPhaseVersion::run($pengmas, $structure, $this->admin))->toThrow(
        ValidationException::class,
        'Tahap Penelitian DIPA dan Pengabdian DIPA sama-sama memakai Formulir pengajuan Proposal Penelitian. Satu periode hanya boleh punya satu tahap per Formulir pengajuan.',
    );
    expect($pengmas->versions()->count())->toBe(1);
});
