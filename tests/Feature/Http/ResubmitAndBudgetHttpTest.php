<?php

// tests/Feature/Http/ResubmitAndBudgetHttpTest.php
//
// Dua Action M1 terakhir yang belum punya jalur: resubmit (BR-SM-03) dan
// penyimpanan line item anggaran (BR-BUD-01/04/09).
//
// Tanpa endpoint resubmit, alur revisi berhenti total: pengajuan yang
// NEEDS_REVISION tidak punya cara kembali ke antrean review.

use App\Models\BudgetComponent;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\UnderReview;

it('[SM-SUBMIT-12] ketua pengaju resubmit lewat endpoint', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => NeedsRevision::class,
    ]);

    $this->actingAs($owner)
        ->post(route('user.submissions.resubmit', $submission))
        ->assertRedirect();

    expect($submission->fresh()->status)->toBeInstanceOf(Resubmitted::class);
});

it('[SM-STATE-03] resubmit mengembalikan ke antrean saat reviewer masih terpasang', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => NeedsRevision::class,
    ]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => User::factory()->create()->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->post(route('user.submissions.resubmit', $submission))
        ->assertRedirect();

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

it('[SM-SUBMIT-12] bukan pemilik ditolak resubmit lewat endpoint', function () {
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id, 'scheme_id' => null, 'status' => NeedsRevision::class,
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('user.submissions.resubmit', $submission))
        ->assertForbidden();
});

it('[BUD-LIMIT-01] line item yang melebihi batas skema ditolak lewat endpoint', function () {
    $owner = User::factory()->create();
    $scheme = Scheme::factory()->create(['max_budget' => 10_000_000]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => $scheme->id, 'status' => Draft::class,
    ]);

    $component = BudgetComponent::factory()->create();

    $this->actingAs($owner)
        ->post(route('user.submissions.budget-items.store', $submission), [
            'budget_component_id' => $component->id,
            'item_name' => 'Sewa alat',
            'volume' => 2,
            'unit' => 'unit',
            'unit_price' => 9_000_000,
        ])
        ->assertSessionHasErrors();

    expect($submission->budgetLineItems()->count())->toBe(0);
});

it('[BUD-LIMIT-02] line item dalam batas tersimpan lewat endpoint', function () {
    $owner = User::factory()->create();
    $scheme = Scheme::factory()->create(['max_budget' => 10_000_000]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => $scheme->id, 'status' => Draft::class,
    ]);

    $component = BudgetComponent::factory()->create();

    $this->actingAs($owner)
        ->post(route('user.submissions.budget-items.store', $submission), [
            'budget_component_id' => $component->id,
            'item_name' => 'Bahan habis pakai',
            'volume' => 2,
            'unit' => 'paket',
            'unit_price' => 5_000_000,
        ])
        ->assertRedirect();

    expect($submission->budgetLineItems()->count())->toBe(1);
});
