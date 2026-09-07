<?php

// tests/Feature/Submission/SubmitUniquenessTest.php
//
// BR-SM-01 — satu submission AKTIF per (peneliti, period, scheme).
// Gerbang di Action mencerminkan predikat index
// one_active_submission_per_period_scheme.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: SubmissionPeriod, 2: Scheme, 3: Scheme} */
function uniquenessSliceContext(): array
{
    return [
        verifiedResearcher(),
        SubmissionPeriod::factory()->create(),
        Scheme::factory()->create(),
        Scheme::factory()->create(),
    ];
}

function uniquenessSliceSubmission(
    User $owner,
    SubmissionPeriod $period,
    ?Scheme $scheme,
    string $state,
    bool $isSubmitted
): FormSubmission {
    return FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme?->id,
        'status' => $state,
        'is_submitted' => $isSubmitted,
    ]);
}

it('[SM-SUBMIT-01] submit pertama ke sebuah skema berhasil', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-02] submit kedua ke skema yang sama ditolak', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    expect(fn () => SubmitFormSubmission::run($draft, $owner))
        ->toThrow(ValidationException::class);
    expect($draft->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-03] dua draft ke skema berbeda boleh hidup bersamaan', function () {
    // `When` skenario ini adalah "membuka daftar pengajuannya", jadi yang
    // dibuktikan harus rute daftar itu sendiri — bukan pemanggilan langsung
    // helper di dalam Action.
    [$owner, $organization] = actingResearcher();

    $formPhase = FormPhase::create(['title' => 'Fase Pengajuan', 'is_active' => true]);

    // Dua Form, keduanya terbuka untuk organisasi Budi lewat FormAccessControl.
    $formInternal = Form::factory()->create();
    $formEksternal = Form::factory()->create();

    $aclInternal = FormAccessControl::create([
        'form_id' => $formInternal->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $aclEksternal = FormAccessControl::create([
        'form_id' => $formEksternal->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);

    $dibangun = buildAccessibleFormPhaseDetail($owner, $organization, $aclInternal, $formPhase);
    buildAccessibleFormPhaseDetail($owner, $organization, $aclEksternal, $formPhase);

    $period = $dibangun['period'];
    SubmissionPeriodPhase::create([
        'submission_period_id' => $period->id,
        'form_phase_id' => $formPhase->id,
    ]);

    $internal = Scheme::factory()->create();
    $eksternal = Scheme::factory()->create();

    $p001 = FormSubmission::factory()->create([
        'form_id' => $formInternal->id,
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $internal->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);
    $p002 = FormSubmission::factory()->create([
        'form_id' => $formEksternal->id,
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $eksternal->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    $response = $this->actingAs($owner)->get(route('user.submissions.index'));

    // Then: "P-001" dan "P-002" sama-sama terbaca.
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // shouldExist=false: config inertia.pages.paths bawaan paket
            // ('js/pages') tidak cocok dengan struktur direktori repo ini.
            ->component('User/Submissions/IndexPage', false)
            ->has('submissionPeriods', 1)
            ->where('submissionPeriods.0.id', $period->id)
            ->where('submissionPeriods.0.user_submissions_count', 2)
            ->where('submissionPeriods.0.user_draft_count', 2));

    // And: tidak ada penolakan karena pengajuan aktif. Predikat index BR-SM-01
    // menyaring `is_submitted = true`, jadi dua DRAFT tidak pernah saling
    // menghalangi — membuka daftarnya tidak memunculkan penolakan apa pun.
    $response->assertSessionHasNoErrors();

    expect($p001->fresh()->status)->toBeInstanceOf(Draft::class)
        ->and($p002->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-04] submit ke skema lain tetap boleh', function () {
    [$owner, $period, $internal, $eksternal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $eksternal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-05] submit ulang ke skema yang sama boleh setelah ditolak', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Rejected::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-06] pengajuan tanpa skema tidak saling menghalangi', function () {
    [$owner, $period] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, null, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, null, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});
