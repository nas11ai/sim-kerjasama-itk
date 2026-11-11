<?php

// ADMIN-08-09 — layar sunting tahap: semua detail tahap (W92), pilihan akses/tipe/tenggat
// (W102), penjaga needs_review (W97) dan penjaga buang detail ber-penugasan (BR-FE-22).

use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
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

/** Detail direview dengan satu form evaluasi aktif. */
function phaseEditReviewedDetail(Form $form, ?FormPhase $phase = null): FormPhaseDetail
{
    $detail = phaseEditDetail($form, $phase, ['needs_review' => true]);
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
        'deadline_type' => $detail->deadline_type->value,
        'deadline_cycle' => $detail->deadline_cycle,
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
                'phaseTypeName', 'needsReview', 'deadlineType', 'deadlineCycle', 'deadlineLabel',
                'submissionCount', 'assignmentCount', 'evaluationFormCount',
            ]))
            ->where('accessOptions.0.formId', $this->form->id)
            ->missing('deadlineOptions'));
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

it('menyimpan ulang detail tidak mengubah jenis langkahnya', function () {
    $detail = phaseEditDetail($this->form);
    $detail->update(['deadline_cycle' => 1]);

    $this->actingAs(phaseEditActor())
        ->put(route('admin.forms.phases.update', [$this->form, $detail->formPhase]), phaseEditPayload([phaseEditRow($detail)]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.forms.phases.index', $this->form))
        ->assertSessionHas('success', 'Tahap diperbarui.');

    expect($detail->fresh()?->deadline_cycle)->toBe(1);
});

it('needs_review tidak bisa dimatikan selama ada form evaluasi aktif', function () {
    $detail = phaseEditReviewedDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.forms.phases.edit', [$this->form, $detail->formPhase]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('phase.details.0.evaluationFormCount', 1));

    $this->put(
        route('admin.forms.phases.update', [$this->form, $detail->formPhase]),
        phaseEditPayload([phaseEditRow($detail, ['needs_review' => false])]),
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

it('kontrak layar buat tahap', function () {
    phaseEditDetail($this->form);
    $foreign = phaseEditDetail(Form::factory()->create(['title' => 'Form Lain']));

    $this->actingAs(phaseEditActor())
        ->get(route('admin.forms.phases.create', $this->form))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseEditPage')
            ->where('form.id', $this->form->id)
            ->where('phase.id', null)
            ->where('phase.title', '')
            ->where('phase.isActive', true)
            ->where('phase.details', [])
            ->where('accessOptions.0.formId', $this->form->id)
            ->where('accessOptions.0.formTitle', $this->form->title)
            ->where('accessOptions', fn ($options) => collect($options)->contains('formId', $foreign->formAccessControl?->form_id))
            ->has('phaseTypeOptions')
            ->missing('deadlineOptions'));
});

it('tahap baru tampil di tab Phases Form-nya', function () {
    $reference = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->post(route('admin.forms.phases.store', $this->form), phaseEditPayload([phaseEditRow($reference, ['id' => null])]))
        ->assertSessionHasNoErrors();

    $created = FormPhase::query()->where('title', 'Tahap Disunting')->firstOrFail();

    $this->get(route('admin.forms.phases.index', $this->form))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('phases', fn ($phases) => collect($phases)->contains('id', $created->id)));
});

it('menyimpan tahap baru mengarah ke editornya dengan pesan W95', function () {
    $reference = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->post(route('admin.forms.phases.store', $this->form), phaseEditPayload([phaseEditRow($reference, ['id' => null])]))
        ->assertRedirect(route('admin.forms.phases.edit', [$this->form, FormPhase::query()->where('title', 'Tahap Disunting')->firstOrFail()]))
        ->assertSessionHas('success', 'Tahap dibuat. Tambahkan form evaluasi untuk detail yang direview.');
});

it('tahap yang hanya memakai Form lain kembali ke tab Phases, bukan 404', function () {
    $foreign = phaseEditDetail(Form::factory()->create());

    $this->actingAs(phaseEditActor())
        ->post(route('admin.forms.phases.store', $this->form), phaseEditPayload([phaseEditRow($foreign, ['id' => null])]))
        ->assertRedirect(route('admin.forms.phases.index', $this->form));
});

it('Form tanpa aturan akses tidak bisa membuat tahap', function () {
    $empty = Form::factory()->create();

    $this->actingAs(phaseEditActor())
        ->get(route('admin.forms.phases.create', $empty))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('accessOptions', fn ($options) => collect($options)->where('formId', $empty->id)->isEmpty()));

    $this->post(route('admin.forms.phases.store', $empty), ['title' => 'Kosong', 'is_active' => true])
        ->assertSessionHasErrors('phase_details');

    expect(FormPhase::query()->where('title', 'Kosong')->exists())->toBeFalse();
});

it('wewenang buat: operator 403', function () {
    $reference = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor('operator'))
        ->get(route('admin.forms.phases.create', $this->form))
        ->assertForbidden();
    $this->post(route('admin.forms.phases.store', $this->form), phaseEditPayload([phaseEditRow($reference, ['id' => null])]))
        ->assertForbidden();

    expect(FormPhase::query()->where('title', 'Tahap Disunting')->exists())->toBeFalse();
});
