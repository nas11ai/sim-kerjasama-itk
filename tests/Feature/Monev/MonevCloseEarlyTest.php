<?php

// tests/Feature/Monev/MonevCloseEarlyTest.php
//
// W135 — BR-SCH-07 (v2.5): Close Early menutup keras tahap pengajuan saja. Siklus monev di
// periode yang sama hanya digerbangi tenggatnya sendiri dan override (BR-MON-05).

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\Override\GrantPhaseOverride;
use App\Actions\Period\CloseSubmissionPeriod;
use App\Data\Request\PhaseOverrideInput;
use App\Enums\MonevCycleState;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Notifications\SubmissionPeriodClosedNotification;
use App\Queries\Monev\MonevCycles;
use App\Support\PhaseAccessGate;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $fx = monevFormFixture();
    $fx['owner']->assignRole('researcher');

    // Batas Submit induk dan Batas Laporan Monev I berada di periode yang sama.
    $this->period = SubmissionPeriod::query()->findOrFail($fx['pengajuan']->submissionDate->submission_period_id);
    $fx['monev1']->submissionDate->update(['submission_period_id' => $this->period->id]);
    $fx['parent']->update(['submission_period_id' => $this->period->id]);

    $this->fx = $fx;
    $this->report = StartChildSubmission::run($fx['parent']->fresh(), $fx['monev1']->fresh(), $fx['owner']);
});

function monevCloseSave(FormSubmission $report, int $fieldId): TestResponse
{
    return test()->post(route('monev.reports.save', $report), [
        'last_updated_at' => $report->fresh()->updated_at?->toISOString(),
        'responses' => [['form_field_id' => $fieldId, 'value' => 'Capaian 45%']],
    ]);
}

it('[MON-CYCLE-16] penutupan paksa periode tidak menutup siklus monev', function () {
    CloseSubmissionPeriod::run($this->period, verifiedResearcher());

    $this->actingAs($this->fx['owner']);
    monevCloseSave($this->report, $this->fx['summary']->id)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(MonevCycles::run($this->fx['parent']->fresh())[0]->state)->toBe(MonevCycleState::Open);
});

it('[FE-OVERRIDE-11] penutupan paksa tetap menutup tahap pengajuan', function () {
    $draft = FormSubmission::factory()->create([
        'form_phase_detail_id' => $this->fx['pengajuan']->id,
        'submission_period_id' => $this->period->id,
    ]);

    CloseSubmissionPeriod::run($this->period, verifiedResearcher());

    expect(PhaseAccessGate::isOpenFor($draft, $this->fx['pengajuan']->fresh()))->toBeFalse();
});

it('[MON-CYCLE-17] laporan monev tidak terarsip saat seluruh tanggal periode lewat', function () {
    $this->travelTo(now()->addMonths(2));
    $report = $this->report->fresh();

    expect($report->resolveIsArchived())->toBeFalse()
        ->and($this->fx['owner']->can('update', $report))->toBeTrue();

    $this->actingAs($this->fx['owner']);
    monevCloseSave($report, $this->fx['summary']->id)->assertForbidden();

    $operator = verifiedResearcher();
    $operator->givePermissionTo('submissions.view-all');
    GrantPhaseOverride::run($report, $this->fx['monev1']->fresh(), $operator, new PhaseOverrideInput(
        'Kendala jaringan saat batas monev berjalan',
        now()->addWeek()->toDateTimeString(),
    ));

    $this->actingAs($this->fx['owner']);
    monevCloseSave($report, $this->fx['summary']->id)
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('Close Early tidak memberi tahu pemilik draft laporan monev', function () {
    Notification::fake();
    $draft = FormSubmission::factory()->create([
        'form_phase_detail_id' => $this->fx['pengajuan']->id,
        'submission_period_id' => $this->period->id,
    ]);

    CloseSubmissionPeriod::run($this->period, verifiedResearcher());

    Notification::assertSentTo($draft->submittedBy, SubmissionPeriodClosedNotification::class);
    Notification::assertNotSentTo($this->fx['owner'], SubmissionPeriodClosedNotification::class);
});
