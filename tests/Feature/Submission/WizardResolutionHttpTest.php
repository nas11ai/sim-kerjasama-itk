<?php

// tests/Feature/Submission/WizardResolutionHttpTest.php
//
// Endpoint langkah 1 (ADR 0012), dan simpan/submit wizard yang
// mendelegasikan resolusi baris ke ResolveWizardSubmission alih-alih
// firstOrCreate() sendiri (#28). Bersama SubmissionEditLockHttpTest, berkas
// ini membuktikan pembedaan 409/403 yang dituntut [SM-STATE-04]: 409 kalau
// belum ada baris sama sekali, 403 kalau baris ada tapi terkunci.

use App\Actions\FormSubmission\SaveSubmissionDraft;
use App\Actions\FormSubmission\StartSubmission;
use App\Data\Request\SubmissionAnswersInput;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

it('membuat draft lewat endpoint langkah 1', function () {
    $fx = wizardFixture();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.store'), [
            'form_id' => $fx['form']->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_id' => $fx['detail']->form_phase_id,
        ])
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $fx['form']->id)->sole();

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->submitted_by)->toBe($fx['user']->id);
});

it('memanggil endpoint langkah 1 dua kali tidak melahirkan draft kembar', function () {
    $fx = wizardFixture();

    $payload = [
        'form_id' => $fx['form']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_id' => $fx['detail']->form_phase_id,
    ];

    $this->actingAs($fx['user'])->post(route('user.submissions.store'), $payload);
    $this->actingAs($fx['user'])->post(route('user.submissions.store'), $payload);

    expect(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(1);
});

it('menolak simpan draft dengan 409 saat belum ada baris sama sekali', function () {
    // Wizard selalu membawa pengajuan di URL; "belum ada baris" hanya tercapai lewat Action-nya.
    $fx = wizardFixture();
    $input = new SubmissionAnswersInput(
        formId: $fx['form']->id,
        submissionPeriodId: $fx['period']->id,
        answers: [wizardTextField($fx['form'])->id => 'Contoh nilai'],
        uploads: [],
        lastUpdatedAt: null,
    );

    expect(fn () => SaveSubmissionDraft::run($input, $fx['user']))->toThrow(ConflictHttpException::class)
        ->and(FormSubmission::where('form_id', $fx['form']->id)->exists())->toBeFalse();
});

it('[FE-TEMPORAL-09] menyimpan draft tidak menjalankan validasi penuh', function () {
    $fx = wizardFixture();
    $type = FieldType::firstOrCreate(['name' => 'file']);
    FormField::create([
        'form_id' => $fx['form']->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'field_type_id' => $type->id,
        'order' => 2,
    ]);

    $started = StartSubmission::run(
        $fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']
    );

    // Field wajib sengaja dibiarkan kosong. Penyimpanan langkah harus tetap lolos.
    $this->actingAs($fx['user'])
        ->post(route('user.submissions.wizard.save', [$started, 2]), wizardPayload(
            $started->updated_at->toISOString(),
            3,
            [['form_field_id' => wizardTextField($fx['form'])->id, 'value' => 'Contoh nilai']],
        ))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $fx['form']->id)->sole();

    expect($submission->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-18] fase yang tidak butuh review tetap mendarat di SUBMITTED', function () {
    $fx = wizardFixture();
    $fx['detail'] = restep($fx['detail'], ['needs_review' => false]);

    StartSubmission::run(
        $fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']
    );

    $submissionAwal = FormSubmission::where('form_id', $fx['form']->id)->sole();

    $this->actingAs($fx['user'])->post(route('user.submissions.wizard.save', [$submissionAwal, 2]), wizardPayload(
        $submissionAwal->updated_at->toIso8601String(),
        7,
        [['form_field_id' => wizardTextField($fx['form'])->id, 'value' => 'Contoh nilai']],
    ))->assertSessionHasNoErrors();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.submit', $submissionAwal))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $fx['form']->id)->sole();

    expect($submission->status)->toBeInstanceOf(Submitted::class);
});
