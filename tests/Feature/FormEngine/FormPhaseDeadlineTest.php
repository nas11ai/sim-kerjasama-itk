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
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');

    $this->detail = FormPhaseDetail::factory()->create();
    $this->phase = $this->detail->formPhase;
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
function deadlineOptionIds(string $url): array
{
    $ids = [];

    test()->actingAs(test()->admin)->get($url)->assertInertia(function ($page) use (&$ids) {
        $ids = collect($page->toArray()['props']['submissionDates'])->pluck('id')->all();
    });

    return $ids;
}

it('rejects moving a detail to a deadline outside the periods using its phase (BR-FE-17)', function () {
    linkedPeriodOf($this->phase);
    $foreign = deadlineOf(SubmissionPeriod::factory()->create());
    $dateId = $this->detail->submission_date_id;

    $this->actingAs($this->admin)
        ->put(route('admin.form-phases.update', $this->phase), deadlinePayload([
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
        ->put(route('admin.form-phases.update', $this->phase), deadlinePayload([
            deadlineRow($this->detail, null),
            deadlineRow($this->detail, $foreign->id, withId: false),
        ]))
        ->assertSessionHasErrors('phase_details.1.submission_date_id');

    expect($this->phase->formPhaseDetails()->count())->toBe(1);
});

it('saves a detail moved to a deadline of a period using its phase (BR-FE-17)', function () {
    $own = deadlineOf(linkedPeriodOf($this->phase));

    $this->actingAs($this->admin)
        ->put(route('admin.form-phases.update', $this->phase), deadlinePayload([
            deadlineRow($this->detail, $own->id),
        ]))
        ->assertSessionHasNoErrors();

    expect($this->detail->fresh()->submission_date_id)->toBe($own->id);
});

it('keeps an unchanged deadline of a detail even if it predates the period link', function () {
    linkedPeriodOf($this->phase);

    $this->actingAs($this->admin)
        ->put(route('admin.form-phases.update', $this->phase), deadlinePayload([
            deadlineRow($this->detail, $this->detail->submission_date_id),
        ]))
        ->assertSessionHasNoErrors();
});

it('rejects a deadline of a force-closed period for a phase no period uses (BR-FE-17)', function () {
    $closed = deadlineOf(SubmissionPeriod::factory()->create(['is_force_closed' => true]));

    $this->actingAs($this->admin)
        ->put(route('admin.form-phases.update', $this->phase), deadlinePayload([
            deadlineRow($this->detail, $closed->id),
        ]))
        ->assertSessionHasErrors('phase_details.0.submission_date_id');
});

it('rejects creating a phase with a deadline of a force-closed period (BR-FE-17)', function () {
    $closed = deadlineOf(SubmissionPeriod::factory()->create(['is_force_closed' => true]));

    $this->actingAs($this->admin)
        ->post(route('admin.form-phases.store'), deadlinePayload([
            deadlineRow($this->detail, $closed->id, withId: false),
        ]))
        ->assertSessionHasErrors('phase_details.0.submission_date_id');

    expect(FormPhase::query()->where('title', 'Tahap Tenggat')->exists())->toBeFalse();
});

it('offers deadlines of periods not force-closed when creating (BR-FE-17)', function () {
    $open = deadlineOf(SubmissionPeriod::factory()->create());
    $closed = deadlineOf(SubmissionPeriod::factory()->create(['is_force_closed' => true]));

    $ids = deadlineOptionIds(route('admin.form-phases.create'));

    expect($ids)->toContain($open->id)->not->toContain($closed->id);
});

it('offers only deadlines of the periods using the phase when editing (BR-FE-17)', function () {
    $own = deadlineOf(linkedPeriodOf($this->phase));
    $other = deadlineOf(SubmissionPeriod::factory()->create());

    $ids = deadlineOptionIds(route('admin.form-phases.edit', $this->phase));

    expect($ids)->toBe([$own->id])->not->toContain($other->id);
});

it('offers deadlines of periods not force-closed when editing a phase no period uses (BR-FE-17)', function () {
    $open = deadlineOf(SubmissionPeriod::factory()->create());
    $closed = deadlineOf(SubmissionPeriod::factory()->create(['is_force_closed' => true]));

    $ids = deadlineOptionIds(route('admin.form-phases.edit', $this->phase));

    expect($ids)->toContain($open->id)->not->toContain($closed->id);
});
