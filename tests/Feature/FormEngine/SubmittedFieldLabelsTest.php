<?php

use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Actions\Monev\SubmitMonevReport;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Monev\MonevReportDetail;
use App\Queries\Review\ReviewerEvaluation;
use App\Queries\Submission\FieldLabelSnapshot;
use App\Queries\Submission\ReadableSubmissionAnswers;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Database\Seeders\PermissionSeeder;

/*
 * BR-FE-35 — teks pertanyaan dan label opsi direkam saat submit/kirim ulang, lalu dibaca
 * selama pengajuan tidak bisa disunting pengisinya. Label diubah langsung di baris hidup:
 * yang diuji pembacanya, bukan jalur terbit.
 */

beforeEach(function () {
    $this->owner = verifiedResearcher();
    $this->submission = FormSubmission::factory()->create(['submitted_by' => $this->owner->id]);
    $formId = $this->submission->form_id;

    $this->budget = FormField::create([
        'form_id' => $formId, 'label' => 'Anggaran', 'is_required' => false, 'order' => 1,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'select'])->id,
    ]);
    $this->own = FormFieldOption::create(['form_field_id' => $this->budget->id, 'label' => 'Mandiri']);
    $this->location = FormField::create([
        'form_id' => $formId, 'label' => 'Lokasi', 'is_required' => false, 'order' => 2,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
    ]);

    FormFieldResponse::create(['form_submission_id' => $this->submission->id, 'form_field_id' => $this->budget->id, 'value' => (string) $this->own->id]);
    FormFieldResponse::create(['form_submission_id' => $this->submission->id, 'form_field_id' => $this->location->id, 'value' => 'Balikpapan']);
});

/** @return array<string, string|null> label => jawaban terbaca */
function readAnswers(FormSubmission $submission): array
{
    $answers = [];

    foreach (ReadableSubmissionAnswers::run($submission->fresh()) as $answer) {
        $answers[$answer->label] = $answer->value;
    }

    return $answers;
}

function relabelBudget(FormField $budget, FormFieldOption $option, string $label, string $optionLabel): void
{
    $budget->update(['label' => $label]);
    $option->update(['label' => $optionLabel]);
}

it('[FE-SNAP-01] keeps the field and option labels seen at submit', function () {
    SubmitFormSubmission::run($this->submission, $this->owner);
    relabelBudget($this->budget, $this->own, 'Total Anggaran', 'Swadana');

    expect(readAnswers($this->submission))->toMatchArray(['Anggaran' => 'Mandiri'])
        ->not->toHaveKey('Total Anggaran');
});

it('[FE-SNAP-02] reads a draft with the current labels', function () {
    relabelBudget($this->budget, $this->own, 'Total Anggaran', 'Swadana');

    expect(readAnswers($this->submission))->toMatchArray(['Total Anggaran' => 'Swadana'])
        ->and($this->submission->fresh()->submitted_field_labels)->toBeNull();
});

it('[FE-SNAP-03] keeps a field deleted after submit and hides a field added after it', function () {
    SubmitFormSubmission::run($this->submission, $this->owner);
    $this->location->delete();
    FormField::create([
        'form_id' => $this->submission->form_id, 'label' => 'Mitra', 'is_required' => false, 'order' => 3,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
    ]);

    expect(readAnswers($this->submission))->toMatchArray(['Lokasi' => 'Balikpapan'])
        ->not->toHaveKey('Mitra');
});

it('[FE-SNAP-04] reads a revision with current labels and records them again on resubmit', function () {
    SubmitFormSubmission::run($this->submission, $this->owner);
    $this->submission->refresh()->status->transitionTo(UnderReview::class);
    $this->submission->refresh()->status->transitionTo(NeedsRevision::class);
    relabelBudget($this->budget, $this->own, 'Total Anggaran', 'Swadana');

    expect(readAnswers($this->submission))->toHaveKey('Total Anggaran');

    ResubmitFormSubmission::run($this->submission->fresh(), $this->owner);
    relabelBudget($this->budget, $this->own, 'Anggaran Usulan', 'Hibah');

    expect(readAnswers($this->submission))->toMatchArray(['Total Anggaran' => 'Swadana'])
        ->not->toHaveKey('Anggaran Usulan');
});

it('[FE-SNAP-05] reads a submission without a record with the current labels', function () {
    $legacy = FormSubmission::factory()->create([
        'form_id' => $this->submission->form_id,
        'status' => Submitted::class,
        'is_submitted' => true,
    ]);
    FormFieldResponse::create(['form_submission_id' => $legacy->id, 'form_field_id' => $this->budget->id, 'value' => (string) $this->own->id]);
    relabelBudget($this->budget, $this->own, 'Total Anggaran', 'Swadana');

    expect($legacy->fresh()->submitted_field_labels)->toBeNull()
        ->and(readAnswers($legacy))->toMatchArray(['Total Anggaran' => 'Swadana']);
});

it('records the label of an option deleted before submit', function () {
    $this->own->delete();

    SubmitFormSubmission::run($this->submission, $this->owner);

    expect(readAnswers($this->submission))->toMatchArray(['Anggaran' => 'Mandiri']);
});

it('records labels when a monev report is submitted', function () {
    $this->seed(PermissionSeeder::class);
    $fx = monevFormFixture();
    $fx['owner']->assignRole('researcher');
    $report = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);
    FormFieldResponse::create(['form_submission_id' => $report->id, 'form_field_id' => $fx['summary']->id, 'value' => 'Capaian 45%']);

    SubmitMonevReport::run($report->fresh(), $fx['owner']);
    $fx['summary']->update(['label' => 'Ringkasan Capaian']);

    $labels = collect(MonevReportDetail::run($fx['owner'], $report->fresh())['answers'])->pluck('value', 'label')->all();

    expect($labels)->toMatchArray(['Ringkasan Kemajuan' => 'Capaian 45%'])
        ->not->toHaveKey('Ringkasan Capaian');
});

it('stores the same labels a reviewer reads', function () {
    SubmitFormSubmission::run($this->submission, $this->owner);
    $this->submission->refresh()->status->transitionTo(UnderReview::class);
    $assignment = SubmissionReviewer::create([
        'form_submission_id' => $this->submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
    relabelBudget($this->budget, $this->own, 'Total Anggaran', 'Swadana');

    $labels = collect(ReviewerEvaluation::run($assignment->fresh(), null)['answers'])->pluck('value', 'label')->all();

    expect($labels)->toBe(readAnswers($this->submission))
        ->toMatchArray(['Anggaran' => 'Mandiri', 'Lokasi' => 'Balikpapan']);
});

it('excludes column-backed fields from the record', function () {
    FormField::create([
        'form_id' => $this->submission->form_id, 'label' => 'Skema', 'is_required' => false, 'order' => 0,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'scheme_selector'])->id,
    ]);

    SubmitFormSubmission::run($this->submission, $this->owner);

    expect(collect($this->submission->fresh()->submitted_field_labels)->pluck('label')->all())
        ->toBe(['Anggaran', 'Lokasi'])
        ->and(FieldLabelSnapshot::run($this->submission->fresh())[0])->toBe([
            'form_field_id' => $this->budget->id,
            'type' => 'select',
            'label' => 'Anggaran',
            'options' => [['id' => $this->own->id, 'label' => 'Mandiri']],
        ]);
});
