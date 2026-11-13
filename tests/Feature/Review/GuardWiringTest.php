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
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\UnderReview;
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
            // Keputusannya ada di payload, bukan di dua boolean terpisah yang
            // tidak dibaca siapa pun: skor yang tidak boleh dilihat tidak
            // dikirim sama sekali.
            ->where('scores.visible', false)
            ->where('scores.scores', [])
            ->etc());
});

// BR-REV-11/12 sampai ke layar. Sebelum irisan ini `canSeePeerScores` dan
// `canSeeReviewerScores` dikirim backend tapi **nol komponen Vue membacanya** —
// jadi di aplikasi yang berjalan, keterlihatan skor tidak mengikuti aturan sama
// sekali. Penjagaannya sekarang di payload: yang tidak boleh dilihat tidak
// dikirim, karena tidak ada tampilan yang bisa menyembunyikannya dengan aman.

it('[REV-VIS-05] peneliti tidak menerima skor sebelum keputusan final', function () {
    $owner = verifiedResearcher();
    $fx = wizardFixture();

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $owner->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => UnderReview::class,
        'is_submitted' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scores.visible', false)
            ->where('scores.scores', [])
            // Ruang kosong tanpa penjelasan membuat orang mengira sistemnya rusak.
            ->where('scores.reason', 'Hasil penilaian terbuka setelah keputusan akhir atas pengajuan ini diambil.')
            ->etc());
});

it('[REV-VIS-06] peneliti menerima skor setelah keputusan final', function () {
    $owner = verifiedResearcher();
    $fx = wizardFixture();

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $owner->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scores.visible', true)
            ->where('scores.reason', null)
            ->etc());
});
