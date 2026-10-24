<?php

// tests/Feature/Submission/ResearcherWizardPagesTest.php
//
// Dasbor peneliti, layar tahap formulir, dan simpan langkah wizard. Pengajuan
// yang ditampilkan dan dihitung adalah baris yang juga ditulis wizard: form,
// periode, dan peneliti yang sama (ResolveWizardSubmission).

use App\Actions\FormSubmission\StartSubmission;
use App\Models\AuditEntry;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\States\Submission\Submitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function wizardPhaseIn(SubmissionPeriod $period, FormPhase $phase): void
{
    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $period->id,
        'form_phase_id' => $phase->id,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function wizardSubmitted(array $fx, SubmissionPeriod $period, array $attributes = []): FormSubmission
{
    return FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $period->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'is_submitted' => true,
        'status' => Submitted::class,
        ...$attributes,
    ]);
}

/** @return array<string, int|bool> */
function dashboardProgress(Collection $periods, int $periodId): array
{
    return $periods->firstWhere('id', $periodId)['form_phases'][0]['user_progress'];
}

it('menghitung kemajuan dasbor dari pengajuan periode itu sendiri', function () {
    $fx = wizardFixture();
    $other = SubmissionPeriod::factory()->create();
    wizardPhaseIn($fx['period'], $fx['phase']);
    wizardPhaseIn($other, $fx['phase']);
    wizardSubmitted($fx, $fx['period']);

    $this->actingAs($fx['user'])
        ->get(route('user.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissionPeriods', fn (Collection $rows) => dashboardProgress($rows, $fx['period']->id)['completed_forms'] === 1
                && dashboardProgress($rows, $other->id)['completed_forms'] === 0
                && dashboardProgress($rows, $other->id)['total_forms'] === 1));
});

it('menghitung sekali form yang terbuka lewat dua kontrol akses', function () {
    $fx = wizardFixture();
    wizardPhaseIn($fx['period'], $fx['phase']);

    // ADR 0013 — form yang sama terbuka lagi lewat permission lain.
    Permission::findOrCreate('submissions.review-self', 'web');
    $fx['user']->givePermissionTo('submissions.review-self');
    $second = FormAccessControl::create([
        'form_id' => $fx['form']->id,
        'permission' => 'submissions.review-self',
        'organization_id' => $fx['detail']->formAccessControl->organization_id,
    ]);
    FormPhaseDetail::create([
        'form_phase_id' => $fx['phase']->id,
        'form_access_control_id' => $second->id,
        'phase_type_id' => $fx['detail']->phase_type_id,
        'order' => 2,
        'submission_date_id' => $fx['detail']->submission_date_id,
    ]);

    $this->actingAs($fx['user'])
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissionPeriods', fn (Collection $rows) => dashboardProgress($rows, $fx['period']->id)['total_forms'] === 1));
});

it('menghitung statistik review dasbor peneliti dari penugasan yang masih berlaku', function () {
    $fx = wizardFixture();
    $reviewer = Reviewer::factory()->create(['user_id' => $fx['user']->id]);

    foreach (['active' => 'pending', 'replaced' => 'completed'] as $status => $evaluation) {
        SubmissionReviewer::create([
            'form_submission_id' => FormSubmission::factory()->create()->id,
            'reviewer_id' => $reviewer->id,
            'evaluation_status' => $evaluation,
            'status' => $status,
        ]);
    }

    $this->actingAs($fx['user'])
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isReviewer', true)
            ->where('reviewStats', [
                'total_assigned' => 1,
                'completed_reviews' => 0,
                'pending_reviews' => 1,
            ]));
});

it('menolak membuka tahap yang tidak terpasang di periode pada URL', function () {
    $fx = wizardFixture();

    $this->actingAs($fx['user'])
        ->get(route('user.form-phase', ['period' => $fx['period']->id, 'phase' => $fx['phase']->id]))
        ->assertRedirect(route('user.dashboard'))
        ->assertSessionHas('error');
});

it('membuka tahap formulir dengan pengajuan periode itu saja', function () {
    $fx = wizardFixture();
    $other = SubmissionPeriod::factory()->create();
    wizardPhaseIn($fx['period'], $fx['phase']);
    wizardPhaseIn($other, $fx['phase']);
    $mine = wizardSubmitted($fx, $fx['period']);

    $this->actingAs($fx['user'])
        ->get(route('user.form-phase', ['period' => $other->id, 'phase' => $fx['phase']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/FormPhase')
            ->where('formPhase.form_access_controls.0.user_submission', null));

    $this->actingAs($fx['user'])
        ->get(route('user.form-phase', ['period' => $fx['period']->id, 'phase' => $fx['phase']->id, 'step' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentStep', 2)
            ->where('formPhase.form_access_controls.0.user_submission.id', $mine->id)
            ->where('formPhase.form_access_controls.0.user_submission.updated_at', $mine->updated_at->toISOString()));
});

it('menyimpan draft dengan satu entri audit', function () {
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $payload = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->updated_at->toISOString());

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(FormFieldResponse::where('form_submission_id', $submission->id)->value('value'))->toBe('Contoh nilai')
        ->and(AuditEntry::query()->auditOnly()
            ->where('description', 'Draft pengajuan disimpan')
            ->where('subject_id', $submission->id)
            ->count())->toBe(1);
});

it('menolak jawaban untuk field milik form lain', function () {
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $payload = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->updated_at->toISOString());
    $foreign = draftPayload(Form::factory()->create(), $fx['period'], $fx['phase'])['responses'][0];
    $payload['responses'][] = ['form_field_id' => $foreign['form_field_id'], 'value' => 'Nyasar'];

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), $payload)
        ->assertSessionHasErrors('responses');

    expect(FormFieldResponse::where('form_submission_id', $submission->id)->count())->toBe(0);
});

it('membatasi ukuran berkas juga saat simpan draft', function () {
    Storage::fake('public');
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $payload = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->updated_at->toISOString());
    $payload['responses'][0]['value'] = UploadedFile::fake()->create('proposal.pdf', 11 * 1024);

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), $payload)
        ->assertSessionHasErrors('responses.0.value');

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('tidak menyimpan berkas dari kiriman yang ditolak tanpa baris pengajuan', function () {
    Storage::fake('public');
    $fx = wizardFixture();
    $type = FieldType::firstOrCreate(['name' => 'file']);
    $field = FormField::create([
        'form_id' => $fx['form']->id,
        'label' => 'Proposal PDF',
        'is_required' => false,
        'field_type_id' => $type->id,
        'order' => 1,
    ]);

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), [
            'form_id' => $fx['form']->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_id' => $fx['phase']->id,
            'responses' => [['form_field_id' => $field->id, 'value' => UploadedFile::fake()->create('proposal.pdf', 10)]],
        ])
        ->assertStatus(409);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('menyimpan berkas unggahan wizard sebagai jawaban field-nya', function () {
    // hasFile('responses') hanya memeriksa elemen tingkat pertama, sedangkan berkas
    // dikirim di responses.*.value — dulu setiap unggahan wizard hilang diam-diam.
    Storage::fake('public');
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $payload = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->updated_at->toISOString());
    $fieldId = $payload['responses'][0]['form_field_id'];
    $payload['responses'][0]['value'] = UploadedFile::fake()->create('proposal.pdf', 10);

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), $payload)
        ->assertSessionHasNoErrors();

    $path = FormFieldResponse::where('form_submission_id', $submission->id)->where('form_field_id', $fieldId)->value('value');

    expect($path)->toStartWith('form-uploads/');
    Storage::disk('public')->assertExists($path);
});

it('tidak lagi mendaftarkan endpoint yang tidak dipanggil layar mana pun', function () {
    expect(Route::has('submissions.index'))->toBeFalse()
        ->and(Route::has('user.form-submission.data'))->toBeFalse();
});
