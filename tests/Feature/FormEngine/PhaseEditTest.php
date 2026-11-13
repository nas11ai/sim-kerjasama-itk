<?php

// ADMIN-08-09 — editor tahap mandiri di /admin/phases: langkah draf atau versi terbaru (BR-FE-33),
// pilihan akses/tipe/tenggat (W102), penjaga needs_review (W97), buang langkah ber-penugasan (BR-FE-22).

use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
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
    return formStepIn($form, $phase, $attributes);
}

/** Detail direview dengan satu form evaluasi aktif. */
function phaseEditReviewedDetail(Form $form, ?FormPhase $phase = null): FormPhaseDetail
{
    $detail = phaseEditDetail($form, $phase, ['needs_review' => true]);
    evaluationFormOn($detail, [], title: 'Lembar Substansi');

    return $detail->fresh() ?? $detail;
}

/** @return array<string, mixed> */
function phaseEditRow(FormPhaseDetail $detail, array $overrides = []): array
{
    return array_merge([
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

/** Bagian tahap yang disunting langsung (BR-FE-33). */
function phaseGeneralPayload(): array
{
    return ['title' => 'Tahap Disunting', 'description' => null, 'is_active' => true];
}

/** Form Evaluasi terpasang `$detail`, dalam bentuk kiriman draf. */
function phaseEditAttachments(FormPhaseDetail $detail): array
{
    return $detail->stepEvaluationForms()->get()->map(fn (StepEvaluationForm $step): array => [
        'review_evaluation_form_id' => $step->review_evaluation_form_id,
        'is_required' => $step->is_required,
        'is_active' => $step->is_active,
    ])->all();
}

it('kontrak layar sunting tahap', function () {
    $detail = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.phases.edit', $detail->formPhase))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseEditPage')
            ->missing('form')
            ->where('phase.id', $detail->form_phase_id)
            ->hasAll(['phase.title', 'phase.description', 'phase.isActive', 'phaseTypeOptions', 'evaluationOptions'])
            ->where('phase.hasDraft', false)
            ->where('phase.versionLabel', 'Versi 1')
            ->where('phase.impact', ['nextLabel' => 'Versi 2', 'pinnedCohorts' => 0])
            ->has('phase.versions.0', fn (AssertableInertia $row) => $row->hasAll(['label', 'publishedAt', 'publishedBy', 'cohortCount']))
            ->has('phase.steps.0', fn (AssertableInertia $row) => $row->hasAll([
                'order', 'formAccessControlId', 'formTitle', 'accessLabel', 'phaseTypeId',
                'phaseTypeName', 'needsReview', 'deadlineType', 'deadlineCycle', 'deadlineLabel',
                'evaluationForms',
            ]))
            ->where('accessOptions.0.formId', $this->form->id)
            ->missing('evaluation')
            ->missing('deadlineOptions'));
});

it('wewenang: peneliti 403', function () {
    $own = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor('researcher'))
        ->get(route('admin.phases.edit', $own->formPhase))
        ->assertForbidden();
    $this->put(route('admin.phases.update', $own->formPhase), phaseGeneralPayload())
        ->assertForbidden();

    expect($own->formPhase?->fresh()?->title)->toBe('Fase Uji Factory');
});

it('menampilkan semua detail tahap, termasuk langkah monev Form lain', function () {
    $first = phaseEditDetail($this->form);
    $other = Form::factory()->create(['title' => 'Form Laporan Lain']);
    // BR-FE-28 — Form lain di tahap ini hanya sebagai Laporan Monev, bukan Pengajuan kedua.
    $second = phaseEditDetail($other, $first->formPhase, ['order' => 2, 'deadline_cycle' => 1]);
    FormSubmission::factory()->count(2)->create(['form_phase_detail_id' => $second->id]);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.phases.edit', $first->formPhase))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('phase.steps', 2)
            ->where('phase.steps.1.formTitle', 'Form Laporan Lain')
            ->where('phase.steps.1.deadlineLabel', 'Laporan Monev I')
            ->where('accessOptions', fn ($options) => collect($options)->contains('formId', $other->id)));
});

it('memperbarui judul tahap tidak menyentuh langkahnya', function () {
    $submission = phaseEditDetail($this->form);
    $detail = phaseEditDetail(Form::factory()->create(), $submission->formPhase, ['deadline_cycle' => 1]);
    $edit = route('admin.phases.edit', $submission->formPhase);

    $this->actingAs(phaseEditActor())
        ->from($edit)
        ->put(route('admin.phases.update', $submission->formPhase), phaseGeneralPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect($edit)
        ->assertSessionHas('success', 'Tahap diperbarui.');

    expect($submission->formPhase->fresh()?->title)->toBe('Tahap Disunting')
        ->and($submission->formPhase->versions()->count())->toBe(1)
        ->and($detail->fresh()?->deadline_cycle)->toBe(1);
});

it('needs_review tidak bisa dimatikan selama ada form evaluasi aktif', function () {
    $detail = phaseEditReviewedDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.phases.edit', $detail->formPhase))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('phase.steps.0.evaluationForms', 1));

    $this->put(
        route('admin.phases.draft.update', $detail->formPhase),
        phaseEditPayload([phaseEditRow($detail, ['needs_review' => false, 'evaluation_forms' => phaseEditAttachments($detail)])]),
    )->assertSessionHasErrors([
        'phase_details.0.needs_review' => 'Detail ini punya 1 form evaluasi aktif, jadi tetap harus direview.',
    ]);

    expect($detail->fresh()?->needs_review)->toBeTrue()
        ->and($detail->formPhase?->draft()->exists())->toBeFalse();
});

it('membuang detail yang form evaluasinya pernah ditugaskan hanya dari versi baru (BR-FE-32)', function () {
    $kept = phaseEditDetail($this->form);
    $dropped = phaseEditReviewedDetail($this->form, $kept->formPhase);
    $evaluation = StepEvaluationForm::query()->where('form_phase_detail_id', $dropped->id)->sole()->reviewEvaluationForm;
    // BR-REV-18 — penugasan form evaluasi selalu milik pengajuan di langkah itu.
    $seat = SubmissionReviewer::create([
        'form_submission_id' => FormSubmission::factory()->create(['form_phase_detail_id' => $dropped->id])->id,
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
        ->put(route('admin.phases.draft.update', $kept->formPhase), phaseEditPayload([phaseEditRow($kept)]))
        ->assertSessionHasNoErrors();
    $this->post(route('admin.phases.publish', $kept->formPhase))->assertSessionHasNoErrors();

    expect($kept->formPhase->currentDetails()->count())->toBe(1)
        ->and(FormPhaseDetail::query()->whereKey($dropped->id)->exists())->toBeTrue()
        ->and(ReviewEvaluationForm::query()->whereKey($evaluation->id)->exists())->toBeTrue()
        ->and(ReviewerFormAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

it('kontrak layar buat tahap', function () {
    phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.phases.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseEditPage')
            ->missing('form')
            ->where('phase.id', null)
            ->where('phase.title', '')
            ->where('phase.isActive', true)
            ->where('phase.steps', [])
            ->where('phase.versionLabel', null)
            ->where('accessOptions.0.formId', $this->form->id)
            ->has('phaseTypeOptions')
            ->missing('deadlineOptions'));
});

it('offers access rules of every active form, ordered by form title', function () {
    $zeta = Form::factory()->create(['title' => 'Zeta Pengabdian']);
    $alpha = Form::factory()->create(['title' => 'Alpha Penelitian']);
    $inactive = Form::factory()->create(['title' => 'Beta Nonaktif', 'is_active' => false]);
    phaseEditDetail($zeta);
    phaseEditDetail($alpha);
    phaseEditDetail($inactive);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.phases.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('accessOptions', fn ($options) => collect($options)->pluck('formTitle')->all()
                === ['Alpha Penelitian', 'Zeta Pengabdian']));
});

it('create editor opened from a Form puts that Form first so its rule is the default', function () {
    $zeta = Form::factory()->create(['title' => 'Zeta Pengabdian']);
    $alpha = Form::factory()->create(['title' => 'Alpha Penelitian']);
    phaseEditDetail($zeta);
    phaseEditDetail($alpha);

    $this->actingAs(phaseEditActor())
        ->get(route('admin.phases.create', ['form' => $zeta->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('accessOptions', fn ($options) => collect($options)->pluck('formTitle')->all()
                === ['Zeta Pengabdian', 'Alpha Penelitian']));

    $this->get(route('admin.phases.create', ['form' => 999999]))->assertNotFound();
});

it('renders the create editor with no access options', function () {
    $this->actingAs(phaseEditActor())
        ->get(route('admin.phases.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseEditPage')
            ->where('accessOptions', []));

    $this->post(route('admin.phases.store'), ['title' => 'Kosong', 'is_active' => true])
        ->assertSessionHasErrors('phase_details');

    expect(FormPhase::query()->where('title', 'Kosong')->exists())->toBeFalse();
});

it('tahap baru tampil di pustaka bertapis Form-nya', function () {
    $reference = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->post(route('admin.phases.store'), phaseEditPayload([phaseEditRow($reference)]))
        ->assertSessionHasNoErrors();

    $created = FormPhase::query()->where('title', 'Tahap Disunting')->firstOrFail();

    $this->get(route('admin.phases.index', ['form' => $this->form->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('phases', fn ($phases) => collect($phases)->contains('id', $created->id)));
});

it('menyimpan tahap baru mengarah ke editornya dengan pesan W95', function () {
    $reference = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->post(route('admin.phases.store'), phaseEditPayload([phaseEditRow($reference)]))
        ->assertRedirect(route('admin.phases.edit', FormPhase::query()->where('title', 'Tahap Disunting')->firstOrFail()))
        ->assertSessionHas('success', 'Tahap dibuat. Susun langkahnya lalu terbitkan.');
});

it('wewenang buat: peneliti 403', function () {
    $reference = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor('researcher'))
        ->get(route('admin.phases.create'))
        ->assertForbidden();
    $this->post(route('admin.phases.store'), phaseEditPayload([phaseEditRow($reference)]))
        ->assertForbidden();

    expect(FormPhase::query()->where('title', 'Tahap Disunting')->exists())->toBeFalse();
});

// B5 — admin yang datang dari pustaka bertapis `?form=` kembali ke pustaka bertapis yang sama.
it('passes the library filter to the editor as returnForm', function () {
    $detail = phaseEditDetail($this->form);
    $this->actingAs(phaseEditActor());

    $this->get(route('admin.phases.edit', ['formPhase' => $detail->formPhase, 'form' => $this->form->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('returnForm', $this->form->id));
    $this->get(route('admin.phases.edit', $detail->formPhase))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('returnForm', null));
    $this->get(route('admin.phases.create', ['form' => $this->form->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('returnForm', $this->form->id));
    $this->get(route('admin.phases.edit', ['formPhase' => $detail->formPhase, 'form' => 999999]))
        ->assertNotFound();
});

// BR-FE-33 — Simpan judul tidak menutup editor: draf dan Terbitkan masih di layar yang sama.
it('stays in the filtered editor after updating the phase name', function () {
    $detail = phaseEditDetail($this->form);
    $edit = route('admin.phases.edit', ['formPhase' => $detail->formPhase, 'form' => $this->form->id]);

    $this->actingAs(phaseEditActor())
        ->from($edit)
        ->put(route('admin.phases.update', $detail->formPhase), [...phaseGeneralPayload(), 'form' => $this->form->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect($edit);
});

it('keeps the library filter when a new phase opens its editor', function () {
    $reference = phaseEditDetail($this->form);

    $this->actingAs(phaseEditActor())
        ->post(route('admin.phases.store'), [
            ...phaseEditPayload([phaseEditRow($reference)]),
            'form' => $this->form->id,
        ])
        ->assertRedirect(route('admin.phases.edit', [
            'formPhase' => FormPhase::query()->where('title', 'Tahap Disunting')->firstOrFail(),
            'form' => $this->form->id,
        ]));
});
