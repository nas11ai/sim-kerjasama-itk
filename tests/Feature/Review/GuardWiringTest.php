<?php

// tests/Feature/Review/GuardWiringTest.php
//
// Tiga guard yang punya unit test lengkap tapi **nol pemanggil produksi**
// sampai irisan ini: ReviewerWorkload (BR-REV-04), WizardStepGate (BR-SM-17),
// dan ScoreVisibility (BR-REV-11/12).
//
// Guard BDD tidak bisa menangkap keadaan ini — skenarionya sudah punya test,
// jadi ledger-nya bersih. Yang menangkapnya hanya audit pemanggil. Berkas ini
// menguji bahwa jalur nyatanya benar-benar lewat sana.

use App\Models\BudgetComponent;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\User;
use App\States\Submission\Draft;
use Inertia\Testing\AssertableInertia as Assert;

it('[SM-WIZARD-08] menyimpan anggaran ditolak selama skema belum dipilih', function () {
    $owner = User::factory()->create();

    $fx = wizardFixture();

    // Form punya scheme_selector, jadi anggarannya memang bergantung skema.
    FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'scheme_selector'])->id,
        'label' => 'Skema',
        'is_required' => true,
        'order' => 1,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $owner->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'scheme_id' => null,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    $this->actingAs($owner)
        ->post(route('user.submissions.budget-items.store', $submission), [
            'budget_component_id' => BudgetComponent::factory()->create()->id,
            'item_name' => 'Bahan habis pakai',
            'volume' => 2,
            'unit' => 'paket',
            'unit_price' => 500000,
        ])
        ->assertSessionHasErrors('scheme_id');

    expect($submission->budgetLineItems()->count())->toBe(0);
});

it('[SM-WIZARD-08] anggaran tersimpan begitu skema terpilih', function () {
    $owner = User::factory()->create();
    $fx = wizardFixture();
    $scheme = Scheme::factory()->create(['max_budget' => 50_000_000]);

    FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'scheme_selector'])->id,
        'label' => 'Skema',
        'is_required' => true,
        'order' => 1,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $owner->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    $this->actingAs($owner)
        ->post(route('user.submissions.budget-items.store', $submission), [
            'budget_component_id' => BudgetComponent::factory()->create()->id,
            'item_name' => 'Bahan habis pakai',
            'volume' => 2,
            'unit' => 'paket',
            'unit_price' => 500000,
        ])
        ->assertSessionHasNoErrors();

    expect($submission->budgetLineItems()->count())->toBe(1);
});

it('[REV-VIS-04] keterlihatan skor ikut terkirim ke layar detail', function () {
    $owner = User::factory()->create();
    $fx = wizardFixture();

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $owner->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    // DRAFT belum keputusan final, jadi peneliti belum melihat skor apa pun
    // (BR-REV-12) — dan ia bukan reviewer, jadi skor rekan pun tertutup.
    $this->actingAs($owner)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSeeReviewerScores', false)
            ->where('canSeePeerScores', false)
            ->etc());
});
