<?php

// tests/Feature/FormEngine/FormPhaseDeadlineTest.php
//
// BR-FE-17 (ADR 0014) — tenggat detail tahap milik periode yang memakai tahap itu.
// Tahap yang belum dipakai periode mana pun boleh memakai tenggat periode yang belum
// ditutup paksa.

use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $this->detail = FormPhaseDetail::factory()->create();
    $this->phase = $this->detail->formPhase;
    $this->form = $this->detail->formAccessControl?->form;
});

function deadlineOf(SubmissionPeriod $period): SubmissionDate
{
    return SubmissionDate::create([
        'type' => DeadlineType::Submit,
        'datetime' => now()->addWeek(),
        'submission_period_id' => $period->id,
    ]);
}

function linkedPeriodOf(FormPhase $phase): SubmissionPeriod
{
    $period = SubmissionPeriod::factory()->create();
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $phase->id]);

    return $period;
}

/** @return array<string, mixed> */
function deadlineRow(FormPhaseDetail $detail, ?int $submissionDateId, bool $withId = true): array
{
    return array_filter([
        'id' => $withId ? $detail->id : null,
        'form_access_control_id' => $detail->form_access_control_id,
        'phase_type_id' => $detail->phase_type_id,
        'order' => 1,
        'needs_review' => false,
        'submission_date_id' => $submissionDateId,
    ], fn ($value) => $value !== null);
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function deadlinePayload(array $rows): array
{
    return ['title' => 'Tahap Tenggat', 'description' => null, 'is_active' => true, 'phase_details' => $rows];
}

/** @return list<int> */
function deadlineOptionIds(string $url, string $prop = 'deadlineOptions'): array
{
    $ids = [];

    test()->actingAs(test()->admin)->get($url)->assertInertia(function ($page) use (&$ids, $prop) {
        $ids = collect($page->toArray()['props'][$prop])->pluck('id')->all();
    });

    return $ids;
}

it('rejects moving a detail to a deadline outside the periods using its phase (BR-FE-17)', function () {
    linkedPeriodOf($this->phase);
    $foreign = deadlineOf(SubmissionPeriod::factory()->create());
    $dateId = $this->detail->submission_date_id;

    $this->actingAs($this->admin)
        ->put(route('admin.forms.phases.update', [$this->form, $this->phase]), deadlinePayload([
            deadlineRow($this->detail, $foreign->id),
        ]))
        ->assertSessionHasErrors('phase_details.0.submission_date_id');

    expect($this->detail->fresh()->submission_date_id)->toBe($dateId)
        ->and($this->phase->fresh()->title)->toBe('Fase Uji Factory');
});

it('rejects a new detail whose deadline is outside the periods using its phase (BR-FE-17)', function () {
    linkedPeriodOf($this->phase);
    $foreign = deadlineOf(SubmissionPeriod::factory()->create());

    $this->actingAs($this->admin)
        ->put(route('admin.forms.phases.update', [$this->form, $this->phase]), deadlinePayload([
            deadlineRow($this->detail, null),
            deadlineRow($this->detail, $foreign->id, withId: false),
        ]))
        ->assertSessionHasErrors('phase_details.1.submission_date_id');

    expect($this->phase->formPhaseDetails()->count())->toBe(1);
});

it('saves a detail moved to a deadline of a period using its phase (BR-FE-17)', function () {
    $own = deadlineOf(linkedPeriodOf($this->phase));

    $this->actingAs($this->admin)
        ->put(route('admin.forms.phases.update', [$this->form, $this->phase]), deadlinePayload([
            deadlineRow($this->detail, $own->id),
        ]))
        ->assertSessionHasNoErrors();

    expect($this->detail->fresh()->submission_date_id)->toBe($own->id);
});

it('keeps an unchanged deadline of a detail even if it predates the period link', function () {
    linkedPeriodOf($this->phase);

    $this->actingAs($this->admin)
        ->put(route('admin.forms.phases.update', [$this->form, $this->phase]), deadlinePayload([
            deadlineRow($this->detail, $this->detail->submission_date_id),
        ]))
        ->assertSessionHasNoErrors();
});

it('rejects a deadline of a force-closed period for a phase no period uses (BR-FE-17)', function () {
    $closed = deadlineOf(SubmissionPeriod::factory()->create(['is_force_closed' => true]));

    $this->actingAs($this->admin)
        ->put(route('admin.forms.phases.update', [$this->form, $this->phase]), deadlinePayload([
            deadlineRow($this->detail, $closed->id),
        ]))
        ->assertSessionHasErrors('phase_details.0.submission_date_id');
});

it('rejects creating a phase with a deadline of a force-closed period (BR-FE-17)', function () {
    $closed = deadlineOf(SubmissionPeriod::factory()->create(['is_force_closed' => true]));

    $this->actingAs($this->admin)
        ->post(route('admin.forms.phases.store', $this->form), deadlinePayload([
            deadlineRow($this->detail, $closed->id, withId: false),
        ]))
        ->assertSessionHasErrors('phase_details.0.submission_date_id');

    expect(FormPhase::query()->where('title', 'Tahap Tenggat')->exists())->toBeFalse();
});

it('offers deadlines of periods not force-closed when creating (BR-FE-17)', function () {
    $open = deadlineOf(SubmissionPeriod::factory()->create());
    $closed = deadlineOf(SubmissionPeriod::factory()->create(['is_force_closed' => true]));

    $ids = deadlineOptionIds(route('admin.forms.phases.create', $this->form));

    expect($ids)->toContain($open->id)->not->toContain($closed->id);
});

it('offers only deadlines of the periods using the phase when editing (BR-FE-17)', function () {
    $own = deadlineOf(linkedPeriodOf($this->phase));
    $other = deadlineOf(SubmissionPeriod::factory()->create());

    $ids = deadlineOptionIds(route('admin.forms.phases.edit', [$this->form, $this->phase]), 'deadlineOptions');

    expect($ids)->toBe([$own->id])->not->toContain($other->id);
});

it('offers deadlines of periods not force-closed when editing a phase no period uses (BR-FE-17)', function () {
    $open = deadlineOf(SubmissionPeriod::factory()->create());
    $closed = deadlineOf(SubmissionPeriod::factory()->create(['is_force_closed' => true]));

    $ids = deadlineOptionIds(route('admin.forms.phases.edit', [$this->form, $this->phase]), 'deadlineOptions');

    expect($ids)->toContain($open->id)->not->toContain($closed->id);
});

// ---------- BR-FE-26: tiga tenggat per detail ----------

/**
 * Tenggat periode baru per label tampilan (BR-FE-25).
 *
 * @return array<string, SubmissionDate>
 */
function typedDeadlines(array $labels, ?SubmissionPeriod $period = null): array
{
    $period ??= SubmissionPeriod::factory()->create();
    $dates = [];

    foreach ($labels as $label) {
        $dates[$label] = SubmissionDate::create([
            ...deadlineTypeOf($label),
            'datetime' => now()->addWeek(),
            'submission_period_id' => $period->id,
        ]);
    }

    return $dates;
}

/** @return array<string, mixed> */
function typedRow(FormPhaseDetail $detail, bool $needsReview, ?SubmissionDate $submit, ?SubmissionDate $evaluation = null, ?SubmissionDate $revision = null): array
{
    return [
        ...deadlineRow($detail, $submit?->id, withId: false),
        'needs_review' => $needsReview,
        'evaluation_date_id' => $evaluation?->id,
        'revision_date_id' => $revision?->id,
    ];
}

function storeTypedPhase(array $row): TestResponse
{
    return test()->actingAs(test()->admin)->post(route('admin.forms.phases.store', test()->form), deadlinePayload([$row]));
}

it('saves the three deadlines of a reviewed detail (BR-FE-26)', function () {
    $d = typedDeadlines(['Batas Submit', 'Batas Evaluasi', 'Batas Revisi']);

    storeTypedPhase(typedRow($this->detail, true, $d['Batas Submit'], $d['Batas Evaluasi'], $d['Batas Revisi']))
        ->assertSessionHasNoErrors();

    $saved = FormPhase::query()->where('title', 'Tahap Tenggat')->sole()->formPhaseDetails()->sole();
    expect($saved->submission_date_id)->toBe($d['Batas Submit']->id)
        ->and($saved->evaluation_date_id)->toBe($d['Batas Evaluasi']->id)
        ->and($saved->revision_date_id)->toBe($d['Batas Revisi']->id);
});

it('[FE-WINDOW-08] rejects a reviewed detail without Batas Evaluasi', function () {
    $d = typedDeadlines(['Batas Submit']);

    storeTypedPhase(typedRow($this->detail, true, $d['Batas Submit']))
        ->assertSessionHasErrors('phase_details.0.evaluation_date_id');

    expect(FormPhase::query()->where('title', 'Tahap Tenggat')->exists())->toBeFalse();
});

it('[FE-WINDOW-09] rejects a Batas Submit used as the Batas Evaluasi', function () {
    $d = typedDeadlines(['Batas Submit']);

    storeTypedPhase(typedRow($this->detail, true, $d['Batas Submit'], $d['Batas Submit']))
        ->assertSessionHasErrors('phase_details.0.evaluation_date_id');
});

it('[FE-WINDOW-09] rejects a Batas Evaluasi used as the Batas Submit', function () {
    $d = typedDeadlines(['Batas Evaluasi']);

    storeTypedPhase(typedRow($this->detail, false, $d['Batas Evaluasi']))
        ->assertSessionHasErrors('phase_details.0.submission_date_id');
});

it('rejects a revision deadline of another type (BR-FE-26)', function () {
    $d = typedDeadlines(['Batas Submit', 'Batas Evaluasi']);

    storeTypedPhase(typedRow($this->detail, false, $d['Batas Submit'], revision: $d['Batas Evaluasi']))
        ->assertSessionHasErrors('phase_details.0.revision_date_id');
});

it('[FE-WINDOW-11] rejects a Monev I detail using the main-cycle Batas Evaluasi', function () {
    $d = typedDeadlines(['Batas Laporan Monev I', 'Batas Evaluasi', 'Batas Evaluasi Monev I']);

    storeTypedPhase(typedRow($this->detail, true, $d['Batas Laporan Monev I'], $d['Batas Evaluasi']))
        ->assertSessionHasErrors('phase_details.0.evaluation_date_id');

    storeTypedPhase(typedRow($this->detail, true, $d['Batas Laporan Monev I'], $d['Batas Evaluasi Monev I']))
        ->assertSessionHasNoErrors();
});

it('rejects deadlines of two different periods on one detail (BR-FE-26)', function () {
    $submit = typedDeadlines(['Batas Submit'])['Batas Submit'];
    $evaluation = typedDeadlines(['Batas Evaluasi'])['Batas Evaluasi'];

    storeTypedPhase(typedRow($this->detail, true, $submit, $evaluation))
        ->assertSessionHasErrors('phase_details.0.evaluation_date_id');
});

it('offers each deadline with its type and cycle so the screen can filter them', function () {
    $d = typedDeadlines(['Batas Evaluasi Monev I']);

    $this->actingAs($this->admin)->get(route('admin.forms.phases.create', $this->form))->assertInertia(fn ($page) => $page
        ->where('deadlineOptions', fn ($options) => collect($options)->contains(fn ($o) => $o['id'] === $d['Batas Evaluasi Monev I']->id
            && $o['type'] === 'evaluation' && $o['cycle'] === 1)));
});

it('saves an Upload Luaran detail bound to Batas Akhir (BR-FE-26, W139)', function () {
    $d = typedDeadlines(['Batas Akhir']);

    storeTypedPhase(typedRow($this->detail, false, $d['Batas Akhir']))->assertSessionHasNoErrors();
});

it('rejects a reviewed Upload Luaran detail (BR-FE-26, W139)', function () {
    $d = typedDeadlines(['Batas Akhir', 'Batas Evaluasi']);

    storeTypedPhase(typedRow($this->detail, true, $d['Batas Akhir'], $d['Batas Evaluasi']))
        ->assertSessionHasErrors('phase_details.0.needs_review');
});
