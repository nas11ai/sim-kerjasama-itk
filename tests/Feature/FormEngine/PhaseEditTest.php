<?php

// ADMIN-08-09 — layar sunting tahap: semua detail tahap (W92), pilihan akses/tipe/tenggat
// (W102), penjaga needs_review (W97) dan penjaga buang detail ber-penugasan (BR-FE-22).

use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->form = Form::factory()->create();
});

function phaseEditActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Detail baru beraturan akses milik $form, di tahap $phase (atau tahap baru). */
function phaseEditDetail(Form $form, ?FormPhase $phase = null, array $attributes = []): FormPhaseDetail
{
    $detail = FormPhaseDetail::factory()->create($attributes);
    $detail->formAccessControl?->update(['form_id' => $form->id]);
    if ($phase !== null) {
        $detail->update(['form_phase_id' => $phase->id]);
    }

    return $detail->fresh() ?? $detail;
}

/** Detail direview: Batas Evaluasi di periode Batas Submit-nya, plus satu form evaluasi aktif. */
function phaseEditReviewedDetail(Form $form, ?FormPhase $phase = null): FormPhaseDetail
{
    $detail = phaseEditDetail($form, $phase, ['needs_review' => true]);
    $detail->update(['evaluation_date_id' => SubmissionDate::create([
        'type' => DeadlineType::Evaluation,
        'datetime' => now()->addMonth(),
        'submission_period_id' => $detail->submissionDate->submission_period_id,
    ])->id]);
    ReviewEvaluationForm::create([
        'title' => 'Lembar Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    return $detail->fresh() ?? $detail;
}

/** @return array<string, mixed> */
function phaseEditRow(FormPhaseDetail $detail, array $overrides = []): array
{
    return array_merge([
        'id' => $detail->id,
        'form_access_control_id' => $detail->form_access_control_id,
        'phase_type_id' => $detail->phase_type_id,
        'order' => 1,
        'needs_review' => $detail->needs_review,
        'submission_date_id' => $detail->submission_date_id,
        'evaluation_date_id' => $detail->evaluation_date_id,
        'revision_date_id' => $detail->revision_date_id,
    ], $overrides);
}

/** @param  list<array<string, mixed>>  $rows */
function phaseEditPayload(array $rows): array
{
    return ['title' => 'Tahap Disunting', 'description' => null, 'is_active' => true, 'phase_details' => $rows];
}

it('kontrak layar sunting tahap', function () {
    $detail = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.forms.phases.edit', [$this->form, $detail->formPhase]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseEditPage')
            ->where('form.id', $this->form->id)
            ->where('phase.id', $detail->form_phase_id)
            ->hasAll(['phase.title', 'phase.description', 'phase.isActive', 'phaseTypeOptions'])
            ->has('phase.details.0', fn (AssertableInertia $row) => $row->hasAll([
                'id', 'order', 'formAccessControlId', 'formTitle', 'accessLabel', 'phaseTypeId',
                'phaseTypeName', 'needsReview', 'submissionDateId', 'evaluationDateId', 'revisionDateId',
                'currentDeadlines', 'submissionCount', 'assignmentCount', 'evaluationFormCount',
            ]))
            ->where('accessOptions.0.formId', $this->form->id)
            ->has('deadlineOptions.0.periodId'));
});

it('wewenang: operator 403; tahap milik Form lain 404', function () {
    $own = phaseEditDetail($this->form);
    $foreign = phaseEditDetail(Form::factory()->create());

    $this->actingAs(phaseEditActor('operator'))
        ->get(route('admin.forms.phases.edit', [$this->form, $own->formPhase]))
        ->assertForbidden();
    $this->put(route('admin.forms.phases.update', [$this->form, $own->formPhase]), phaseEditPayload([phaseEditRow($own)]))
        ->assertForbidden();

    $this->actingAs(phaseEditActor())
        ->get(route('admin.forms.phases.edit', [$this->form, $foreign->formPhase]))
        ->assertNotFound();
    $this->put(route('admin.forms.phases.update', [$this->form, $foreign->formPhase]), phaseEditPayload([phaseEditRow($foreign)]))
        ->assertNotFound();

    expect($own->formPhase?->fresh()?->title)->toBe('Fase Uji Factory')
        ->and($foreign->formPhase?->fresh()?->title)->toBe('Fase Uji Factory');
});

it('menampilkan semua detail tahap, termasuk detail Form lain', function () {
    $first = phaseEditDetail($this->form);
    $other = Form::factory()->create(['title' => 'Form Laporan Lain']);
    $second = phaseEditDetail($other, $first->formPhase);
    FormSubmission::factory()->count(2)->create(['form_phase_detail_id' => $second->id]);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.forms.phases.edit', [$this->form, $first->formPhase]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('phase.details', 2)
            ->where('phase.details.1.id', $second->id)
            ->where('phase.details.1.formTitle', 'Form Laporan Lain')
            ->where('phase.details.1.submissionCount', 2)
            ->where('accessOptions', fn ($options) => collect($options)->contains('formId', $other->id)));
});

it('menyimpan ulang detail bertenggat lama tidak mengubah tenggatnya', function () {
    $detail = phaseEditDetail($this->form);
    $dateId = $detail->submission_date_id;
    // Tahap ditautkan ke periode baru; tenggat lama tidak lagi termasuk pilihan (W102).
    $period = SubmissionPeriod::factory()->create();
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $detail->form_phase_id]);
    $url = route('admin.forms.phases.edit', [$this->form, $detail->formPhase]);

    $this->actingAs(phaseEditActor())->get($url)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('deadlineOptions', fn ($options) => !collect($options)->contains('id', $dateId))
        ->where('phase.details.0.currentDeadlines.0.id', $dateId)
        ->where('phase.details.0.currentDeadlines.0.periodId', $detail->submissionDate->submission_period_id));

    $this->put(route('admin.forms.phases.update', [$this->form, $detail->formPhase]), phaseEditPayload([phaseEditRow($detail)]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.forms.phases.index', $this->form))
        ->assertSessionHas('success', 'Tahap diperbarui.');

    expect($detail->fresh()?->submission_date_id)->toBe($dateId);
});

it('needs_review tidak bisa dimatikan selama ada form evaluasi aktif', function () {
    $detail = phaseEditReviewedDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.forms.phases.edit', [$this->form, $detail->formPhase]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('phase.details.0.evaluationFormCount', 1));

    $this->put(
        route('admin.forms.phases.update', [$this->form, $detail->formPhase]),
        phaseEditPayload([phaseEditRow($detail, ['needs_review' => false, 'evaluation_date_id' => null])]),
    )->assertSessionHasErrors([
        'phase_details.0.needs_review' => 'Detail ini punya 1 form evaluasi aktif, jadi tetap harus direview.',
    ]);

    expect($detail->fresh()?->needs_review)->toBeTrue()
        ->and($detail->formPhase?->fresh()?->title)->toBe('Fase Uji Factory');
});

it('membuang detail yang form evaluasinya pernah ditugaskan ditolak', function () {
    $kept = phaseEditDetail($this->form);
    $dropped = phaseEditReviewedDetail($this->form, $kept->formPhase);
    $evaluation = ReviewEvaluationForm::query()->where('form_phase_detail_id', $dropped->id)->sole();
    // Kursi reviewer di pengajuan detail lain: detail yang dibuang tidak punya pengajuan.
    $seat = SubmissionReviewer::create([
        'form_submission_id' => FormSubmission::factory()->create()->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $seat->id,
        'review_evaluation_form_id' => $evaluation->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => false,
    ]);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.forms.phases.edit', [$this->form, $kept->formPhase]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('phase.details.1.assignmentCount', 1)
            ->where('phase.details.1.submissionCount', 0));

    $this->putJson(route('admin.forms.phases.update', [$this->form, $kept->formPhase]), phaseEditPayload([phaseEditRow($kept)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id' => '1 penugasan form evaluasi']);

    expect(FormPhaseDetail::query()->whereKey($dropped->id)->exists())->toBeTrue()
        ->and(ReviewEvaluationForm::query()->whereKey($evaluation->id)->exists())->toBeTrue()
        ->and(ReviewerFormAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
});
