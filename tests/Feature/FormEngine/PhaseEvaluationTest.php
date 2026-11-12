<?php

// ADMIN-08-09 — form evaluasi reviewer di layar sunting tahap: satu bagian per detail yang
// direview, tempat memasang Form Evaluasi pustaka lewat route ber-detail (BR-FE-30, W100).

use App\Actions\Review\AssignEvaluationForm;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Form\PhaseEvaluationForms;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->form = Form::factory()->create(['title' => 'Form Proposal']);
});

function phaseEvaluationActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Detail beraturan akses milik $form, di tahap $phase (atau tahap baru). */
function phaseEvaluationDetail(Form $form, bool $needsReview, ?FormPhase $phase = null): FormPhaseDetail
{
    return formStepIn($form, $phase, ['needs_review' => $needsReview]);
}

it('kontrak bagian form evaluasi', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $evaluation = evaluationFormOn($detail, title: 'Lembar Substansi');
    publishVersion($evaluation, [['type' => 'text', 'label' => 'Metodologi']]);
    $other = ReviewEvaluationForm::create(['title' => 'Lembar Anggaran']);
    publishVersion($other, [['type' => 'text', 'label' => 'Kewajaran']]);
    ReviewEvaluationForm::create(['title' => 'Lembar Belum Terbit']);

    $this->actingAs(phaseEvaluationActor())
        ->get(route('admin.phases.edit', $detail->formPhase))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/PhaseEditPage')
            ->missing('fieldTypeOptions')
            // BR-FE-30 — hanya Form Evaluasi terbit yang bisa dipasang, urut judul.
            ->where('evaluationOptions', [
                ['value' => (string) $other->id, 'label' => 'Lembar Anggaran'],
                ['value' => (string) $evaluation->id, 'label' => 'Lembar Substansi'],
            ])
            ->has('evaluation.0', fn (AssertableInertia $section) => $section
                ->where('detailId', $detail->id)
                ->where('detailLabel', "{$detail->phaseType?->name} — Form Proposal")
                ->where('reviewsInProgress', 0)
                ->where('attached', [[
                    'id' => $evaluation->stepEvaluationForms()->sole()->id,
                    'formId' => $evaluation->id,
                    'title' => 'Lembar Substansi',
                    'versionLabel' => 'Versi 2',
                    'isRequired' => true,
                    'isActive' => true,
                    'assignmentCount' => 0,
                ]])));
});

it('hanya detail yang direview punya bagian form evaluasi', function () {
    $reviewed = phaseEvaluationDetail($this->form, true);
    phaseEvaluationDetail($this->form, false, $reviewed->formPhase);

    $this->actingAs(phaseEvaluationActor())
        ->get(route('admin.phases.edit', $reviewed->formPhase))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('phase.details', 2)
            ->has('evaluation', 1)
            ->where('evaluation.0.detailId', $reviewed->id)
            ->where('evaluation.0.attached', []));

    // W95 — mode buat belum punya detail tersimpan, jadi belum ada bagian evaluasi.
    $this->get(route('admin.phases.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('evaluation', [])
            ->where('evaluationOptions', []));
});

it('menghitung penugasan per pemasangan, hanya pada pengajuan di langkah itu', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $other = phaseEvaluationDetail($this->form, true);
    $evaluation = evaluationFormOn($detail, title: 'Lembar Substansi');
    StepEvaluationForm::create(['form_phase_detail_id' => $other->id, 'review_evaluation_form_id' => $evaluation->id, 'order' => 1]);
    foreach ([$detail, $detail, $other] as $step) {
        $seat = SubmissionReviewer::create([
            'form_submission_id' => FormSubmission::factory()->create(['form_phase_detail_id' => $step->id])->id,
            'reviewer_id' => Reviewer::factory()->create()->id,
            'evaluation_status' => 'pending',
            'status' => 'active',
        ]);
        AssignEvaluationForm::run($seat, $evaluation->id);
    }

    $sections = collect(PhaseEvaluationForms::run($detail->formPhase))->keyBy('detailId');

    expect($sections[$detail->id]->attached[0]->assignmentCount)->toBe(2);
});

it('wewenang pasang: operator 403', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $evaluation = ReviewEvaluationForm::create(['title' => 'Lembar Substansi']);
    publishVersion($evaluation, [['type' => 'text', 'label' => 'Catatan']]);

    $this->actingAs(phaseEvaluationActor('operator'))
        ->post(route('admin.phase-details.evaluation-forms.store', $detail), ['review_evaluation_form_id' => $evaluation->id])
        ->assertForbidden();

    expect(StepEvaluationForm::query()->count())->toBe(0);
});

it('memasang form evaluasi di detail dari route-nya', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $other = phaseEvaluationDetail($this->form, true);
    $evaluation = ReviewEvaluationForm::create(['title' => 'Lembar Kelayakan']);
    publishVersion($evaluation, [['type' => 'text', 'label' => 'Kelayakan']]);

    $this->actingAs(phaseEvaluationActor())
        ->from(route('admin.phases.edit', $detail->formPhase))
        ->post(route('admin.phase-details.evaluation-forms.store', $detail), [
            'review_evaluation_form_id' => $evaluation->id,
            'form_phase_detail_id' => $other->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.phases.edit', $detail->formPhase))
        ->assertSessionHas('success', 'Form evaluasi dipasang.');

    // BR-FE-32 — pemasangan menerbitkan versi berikutnya; langkah salinannya yang memasang.
    expect($evaluation->stepEvaluationForms()->sole()->form_phase_detail_id)
        ->toBe($detail->formPhase->currentDetails()->get()->firstWhere('form_access_control_id', $detail->form_access_control_id)?->id);
});

it('menolak memasang di detail yang tidak direview', function () {
    $detail = phaseEvaluationDetail($this->form, false);
    $evaluation = ReviewEvaluationForm::create(['title' => 'Lembar Kelayakan']);
    publishVersion($evaluation, [['type' => 'text', 'label' => 'Kelayakan']]);

    $this->actingAs(phaseEvaluationActor())
        ->post(route('admin.phase-details.evaluation-forms.store', $detail), ['review_evaluation_form_id' => $evaluation->id])
        ->assertSessionHasErrors('form_phase_detail_id');

    expect(StepEvaluationForm::query()->count())->toBe(0);
});

/** Pengajuan pada $detail berstatus $state dengan reviewer berstatus $reviewerStatuses. */
function phaseEvaluationReviewed(FormPhaseDetail $detail, string $state, array $reviewerStatuses): void
{
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id, 'status' => $state]);
    foreach ($reviewerStatuses as $status) {
        SubmissionReviewer::create([
            'form_submission_id' => $submission->id,
            'reviewer_id' => Reviewer::factory()->create()->id,
            'evaluation_status' => 'pending',
            'status' => $status,
        ]);
    }
}

// W158 — form ditugaskan otomatis hanya saat reviewer ditugaskan; kartu menghitung pengajuan yang terlewat.
it('reviewsInProgress menghitung pengajuan ber-reviewer aktif yang belum diputus pada detail itu', function () {
    $detail = phaseEvaluationDetail($this->form, true);
    $other = phaseEvaluationDetail($this->form, true, $detail->formPhase);

    phaseEvaluationReviewed($detail, UnderReview::class, ['active']);
    phaseEvaluationReviewed($detail, Submitted::class, ['active']);
    phaseEvaluationReviewed($detail, NeedsRevision::class, ['active']);
    phaseEvaluationReviewed($detail, Resubmitted::class, ['active']);
    // Dua reviewer aktif pada satu pengajuan tetap satu pengajuan.
    phaseEvaluationReviewed($detail, UnderReview::class, ['active', 'active']);
    phaseEvaluationReviewed($detail, Submitted::class, []);
    phaseEvaluationReviewed($detail, Approved::class, ['active']);
    phaseEvaluationReviewed($detail, UnderReview::class, ['replaced']);
    phaseEvaluationReviewed($other, UnderReview::class, ['active']);

    $sections = collect(PhaseEvaluationForms::run($detail->formPhase))->keyBy('detailId');

    expect($sections[$detail->id]->reviewsInProgress)->toBe(5)
        ->and($sections[$other->id]->reviewsInProgress)->toBe(1);
});
