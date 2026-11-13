<?php

// tests/Feature/Review/EvaluationOverrideTest.php
//
// W25 — Perpanjang Tenggat tetap satu mekanisme (ADR 0014): override yang berlaku pada detail
// pemilik form evaluasi juga membuka kembali evaluasi reviewer yang lewat tenggat. Tanpa
// override, FE-WINDOW-02 tetap berlaku (ReviewerEvaluationPageTest).

use App\Models\FieldType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormField;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Cache::flush();

    // Detail "Pengajuan" — form evaluasi reviewer melekat di sini.
    $this->detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $this->submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $this->detail->id,
        'submitted_by' => User::factory()->create()->id,
        'status' => UnderReview::class,
    ]);
    $this->reviewerUser = User::factory()->create(['name' => 'Sari'])
        ->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));
    $seat = SubmissionReviewer::create([
        'form_submission_id' => $this->submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $this->reviewerUser->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
    $form = evaluationFormOn($this->detail, [], title: 'Formulir Substansi');
    $this->field = ReviewFormField::create([
        'review_evaluation_form_version_id' => $form->latestVersion->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'label' => 'Catatan',
        'is_required' => false,
        'order' => 1,
    ]);
    // "Batas Evaluasi" pada 2026-03-20.
    $this->started = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $seat->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => Carbon::parse('2026-03-01 09:00:00'),
        'due_date' => Carbon::parse('2026-03-20 23:59:59'),
        'is_active' => true,
    ]);
    ReviewFormResponse::create(['reviewer_form_assignment_id' => $this->started->id, 'review_evaluation_form_version_id' => $this->started->reviewEvaluationForm->latestVersion->id, 'status' => 'draft']);
});

function evaluationOverride(FormSubmission $submission, FormPhaseDetail $detail, array $attributes = []): FormSubmissionOverride
{
    return FormSubmissionOverride::create([
        'form_submission_id' => $submission->id,
        'form_phase_detail_id' => $detail->id,
        'granted_by' => User::factory()->create()->id,
        'reason' => 'Penilai sakit.',
        'expires_at' => Carbon::parse('2026-03-31 23:59:59'),
        'is_active' => true,
        ...$attributes,
    ]);
}

function evaluationOverrideState(mixed $test): string
{
    $state = '';
    $test->actingAs($test->reviewerUser)
        ->get(route('reviewer.assignments.evaluation', $test->submission))
        ->assertInertia(function (Assert $page) use (&$state) {
            $state = $page->toArray()['props']['forms'][0]['state'];
        });

    return $state;
}

it('[FE-WINDOW-05] override yang berlaku membuka kembali evaluasi yang lewat batas', function () {
    $this->travelTo(Carbon::parse('2026-03-25 09:00:00'));
    evaluationOverride($this->submission, $this->detail);

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.save-draft', $this->started), ['responses' => [$this->field->id => 'Baik']])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(evaluationOverrideState($this))->toBe('draft');
});

it('[FE-WINDOW-06] override yang kedaluwarsa tidak membuka kembali evaluasi', function () {
    $this->travelTo(Carbon::parse('2026-04-02 09:00:00'));
    evaluationOverride($this->submission, $this->detail);

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.save-draft', $this->started), ['responses' => [$this->field->id => 'Baik']])
        ->assertForbidden();

    expect(evaluationOverrideState($this))->toBe('overdue');
});

it('[FE-WINDOW-07] override fase lain tidak membuka kembali evaluasi', function () {
    $this->travelTo(Carbon::parse('2026-03-25 09:00:00'));
    // Detail "Laporan Monev I" — override di sana tidak melebar ke detail pemilik form (BR-FE-10).
    evaluationOverride($this->submission, FormPhaseDetail::factory()->create());

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.save-draft', $this->started), ['responses' => [$this->field->id => 'Baik']])
        ->assertForbidden();

    expect(evaluationOverrideState($this))->toBe('overdue');
});

it('[FE-OVERRIDE-05] override yang dicabut tidak membuka kembali evaluasi', function () {
    $this->travelTo(Carbon::parse('2026-03-25 09:00:00'));
    evaluationOverride($this->submission, $this->detail, ['is_active' => false]);

    $this->actingAs($this->reviewerUser)
        ->post(route('reviewer.evaluation-form.save-draft', $this->started), ['responses' => [$this->field->id => 'Baik']])
        ->assertForbidden();

    expect(evaluationOverrideState($this))->toBe('overdue');
});
