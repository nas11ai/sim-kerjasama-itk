<?php

// tests/Feature/FormEngine/PhaseStepKindTest.php
//
// BR-FE-07 + BR-FE-26 (ADR 0019) — langkah fase menyebut jenis tenggat dan siklusnya; tanggalnya
// dibaca dari kalender periode milik pengajuan, jadi admin tidak lagi memilih tanggal.

use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
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
    // BR-FE-28 — langkah bersiklus memakai Form selain Form pengajuan, di belakang langkah Pengajuan.
    $this->monev = FormPhaseDetail::factory()->create([
        'form_phase_id' => $this->phase->id, 'order' => 2, 'deadline_cycle' => 1,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stepKindRow(FormPhaseDetail $detail, array $overrides = []): array
{
    return $overrides + [
        'form_access_control_id' => $detail->form_access_control_id,
        'phase_type_id' => $detail->phase_type_id,
        'order' => 1,
        'needs_review' => false,
        'deadline_type' => 'submit',
        'deadline_cycle' => null,
    ];
}

/** @param  list<array<string, mixed>>  $rows */
function storeStepKinds(array $rows): TestResponse
{
    return test()->actingAs(test()->admin)->post(route('admin.phases.store'), [
        'title' => 'Tahap Jenis', 'description' => null, 'is_active' => true, 'phase_details' => $rows,
    ]);
}

it('menyimpan langkah dengan jenis dan siklus', function () {
    storeStepKinds([stepKindRow($this->detail), stepKindRow($this->monev, ['deadline_cycle' => 2])])
        ->assertSessionHasNoErrors();

    $saved = FormPhase::query()->where('title', 'Tahap Jenis')->sole()->formPhaseDetails()
        ->where('form_access_control_id', $this->monev->form_access_control_id)->sole();

    expect($saved->deadline_type)->toBe(DeadlineType::Submit)
        ->and($saved->deadline_cycle)->toBe(2);
});

it('merevisi jenis dan siklus langkah lama', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.phases.update', $this->phase), [
            'title' => 'Tahap Jenis', 'is_active' => true,
            'phase_details' => [
                stepKindRow($this->detail, ['id' => $this->detail->id]),
                stepKindRow($this->monev, ['id' => $this->monev->id, 'deadline_cycle' => 2]),
            ],
        ])
        ->assertSessionHasNoErrors();

    // BR-FE-32 — revisi menerbitkan versi baru; langkah versi lama tidak berubah.
    expect($this->phase->currentDetails()->pluck('deadline_cycle')->all())->toBe([null, 2])
        ->and($this->monev->fresh()?->deadline_cycle)->toBe($this->monev->deadline_cycle);
});

it('menolak langkah tanpa jenis atau dengan jenis kalender lain', function () {
    $row = stepKindRow($this->detail);
    unset($row['deadline_type']);

    storeStepKinds([$row])->assertSessionHasErrors('phase_details.0.deadline_type');
    storeStepKinds([stepKindRow($this->detail, ['deadline_type' => 'evaluation'])])
        ->assertSessionHasErrors('phase_details.0.deadline_type');
    storeStepKinds([stepKindRow($this->detail, ['deadline_cycle' => 0])])
        ->assertSessionHasErrors('phase_details.0.deadline_cycle');
});

it('[FE-WINDOW-09] menolak Upload Luaran bersiklus atau direview', function () {
    $submission = stepKindRow($this->detail);

    storeStepKinds([$submission, stepKindRow($this->monev, ['deadline_type' => 'output', 'deadline_cycle' => 1])])
        ->assertSessionHasErrors(['phase_details.1.deadline_cycle' => 'Upload Luaran tidak bersiklus.']);

    storeStepKinds([$submission, stepKindRow($this->monev, ['deadline_type' => 'output', 'needs_review' => true])])
        ->assertSessionHasErrors(['phase_details.1.needs_review' => 'Detail Upload Luaran tidak direview.']);

    expect(FormPhase::query()->where('title', 'Tahap Jenis')->exists())->toBeFalse();

    storeStepKinds([$submission, stepKindRow($this->monev, ['deadline_type' => 'output'])])->assertSessionHasNoErrors();
});

it('editor tahap mengirim jenis, siklus, dan label langkah tanpa pilihan tanggal', function () {
    $this->detail = restep($this->detail, ['deadline_type' => DeadlineType::Submit, 'deadline_cycle' => 2]);

    $this->actingAs($this->admin)
        ->get(route('admin.phases.edit', $this->phase))
        ->assertInertia(fn ($page) => $page
            ->missing('deadlineOptions')
            ->where('phase.details.0.deadlineType', 'submit')
            ->where('phase.details.0.deadlineCycle', 2)
            ->where('phase.details.0.deadlineLabel', 'Laporan Monev II')
            ->missing('phase.details.0.submissionDateId')
            ->missing('phase.details.0.currentDeadlines'));
});
