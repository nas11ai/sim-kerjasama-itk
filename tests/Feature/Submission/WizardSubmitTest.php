<?php

// tests/Feature/Submission/WizardSubmitTest.php
//
// Submit dari langkah 7 lewat route v2. Jalur legacy menyimpan draft dulu sehingga ikut
// diperiksa PhaseAccessGate; route ini tidak menyimpan apa pun, jadi gerbang itu harus
// ada di SubmitFormSubmission sendiri (BR-FE-08).

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\VerificationStatus;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;

/** @return array{fx: array<string, mixed>, submission: FormSubmission} */
function wizardSubmitFixture(): array
{
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    return ['fx' => $fx, 'submission' => $submission];
}

it('[SM-SUBMIT-09] ketua submit dari langkah 7', function () {
    ['fx' => $fx, 'submission' => $submission] = wizardSubmitFixture();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.submit', $submission))
        ->assertRedirect(route('user.submissions.show', $submission))
        ->assertSessionHas('success', 'Pengajuan berhasil dikirim.');

    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-07] [SM-SUBMIT-16] tanpa proposal PDF ditolak dengan error per field', function () {
    ['fx' => $fx, 'submission' => $submission] = wizardSubmitFixture();
    $field = FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'file'])->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'order' => 1,
    ]);
    // Field wajib sejak sebelum draft lahir (BR-FE-11).
    $field->forceFill(['created_at' => now()->subDay(), 'required_since' => now()->subDay()])->save();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.submit', $submission))
        ->assertRedirect(route('user.submissions.wizard', [$submission, 7]))
        ->assertSessionHasErrors('field_'.$field->id);

    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class)
        ->and($fx['user']->can('update', $submission->fresh()))->toBeTrue();
});

it('[SM-SUBMIT-15] pengajuan aktif yang sama disebut', function () {
    ['fx' => $fx, 'submission' => $submission] = wizardSubmitFixture();
    $scheme = Scheme::factory()->create();
    $active = FormSubmission::factory()->create([
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'scheme_id' => $scheme->id,
        'status' => Submitted::class,
        'is_submitted' => true,
    ]);
    $submission->update(['scheme_id' => $scheme->id]);

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.submit', $submission))
        ->assertSessionHasErrors('scheme_id');

    expect(session('errors')->first('scheme_id'))->toContain("#{$active->id}", route('user.submissions.show', $active));
});

it('[IAM-VERIFY-02] akun pending ditolak', function () {
    ['fx' => $fx, 'submission' => $submission] = wizardSubmitFixture();
    $fx['user']->userProfile->update(['verification_status' => VerificationStatus::Pending->value]);

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.submit', $submission))
        ->assertSessionHasErrors('verification_status');

    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[BR-FE-08] submit lewat batas fase ditolak', function () {
    ['fx' => $fx, 'submission' => $submission] = wizardSubmitFixture();
    // Periode masih terbuka lewat tanggal lain; hanya batas fase pengajuan ini yang lewat.
    SubmissionDate::create([
        'submission_date_label_id' => SubmissionDateLabel::firstOrCreate(['name' => 'Batas Laporan'])->id,
        'submission_period_id' => $fx['period']->id,
        'datetime' => now()->addMonths(2),
    ]);
    $this->travel(8)->days();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.submit', $submission))
        ->assertForbidden();

    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-WIZARD-01] langkah 1 mengarahkan ke langkah 2', function () {
    $fx = wizardFixture();

    $response = $this->actingAs($fx['user'])->post(route('user.submissions.store'), [
        'form_id' => $fx['form']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_id' => $fx['phase']->id,
    ]);

    $created = FormSubmission::where('form_id', $fx['form']->id)->sole();

    $response->assertRedirect(route('user.submissions.wizard', [$created, 2]));
    expect($created->last_completed_step)->toBe(1);
});
