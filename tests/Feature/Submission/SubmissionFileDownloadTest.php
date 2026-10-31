<?php

// tests/Feature/Submission/SubmissionFileDownloadTest.php
//
// BR-SM-07, fe-v2-design W15 — berkas jawaban pengajuan tinggal di disk privat dan hanya
// terbaca lewat route unduh yang diperiksa terhadap pengajuan pemiliknya.

use App\Actions\FormSubmission\StartSubmission;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @return array{0: FormSubmission, 1: FormFieldResponse} */
function uploadedProposal(string $type = 'file', string $value = 'form-uploads/proposal.pdf'): array
{
    $submission = FormSubmission::factory()->create();
    $field = FormField::create([
        'form_id' => $submission->form_id,
        'field_type_id' => FieldType::firstOrCreate(['name' => $type])->id,
        'label' => 'Proposal',
        'is_required' => false,
        'order' => 1,
    ]);
    $response = FormFieldResponse::create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => $value]);

    return [$submission, $response];
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

it('[BR-SM-07] proposal yang diunggah tersimpan di disk privat, bukan public', function () {
    $fx = wizardFixture();
    $field = FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'file'])->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'order' => 1,
    ]);
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $this->actingAs($fx['user'])->post(route('user.submissions.wizard.save', [$submission, 6]), [
        'goto' => 7,
        'last_updated_at' => $submission->updated_at->toISOString(),
        'responses' => [['form_field_id' => $field->id, 'value' => UploadedFile::fake()->create('proposal.pdf', 10)]],
    ])->assertSessionHasNoErrors();

    $path = $submission->formFieldResponses()->where('form_field_id', $field->id)->value('value');

    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

it('ketua mengunduh proposalnya', function () {
    [$submission, $response] = uploadedProposal();
    Storage::disk('local')->put($response->value, 'isi');

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.files.show', [$submission, $response]))
        ->assertOk()
        ->assertDownload('proposal.pdf');
});

it('[SM-ACCESS-03] peneliti lain ditolak 403', function () {
    [$submission, $response] = uploadedProposal();
    Storage::disk('local')->put($response->value, 'isi');

    $this->actingAs(verifiedResearcher())
        ->get(route('user.submissions.files.show', [$submission, $response]))
        ->assertForbidden();
});

it('jawaban dari pengajuan lain 404', function () {
    [$mine] = uploadedProposal();
    [, $theirs] = uploadedProposal(value: 'form-uploads/milik-orang.pdf');
    Storage::disk('local')->put($theirs->value, 'isi');

    $this->actingAs($mine->submittedBy)
        ->get(route('user.submissions.files.show', [$mine, $theirs]))
        ->assertNotFound();
});

it('jawaban bukan berkas 404', function () {
    [$submission, $response] = uploadedProposal(type: 'text', value: 'Judul biasa');

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.files.show', [$submission, $response]))
        ->assertNotFound();
});
