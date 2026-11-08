<?php

// tests/Feature/Submission/ResearcherWizardPagesTest.php
//
// Simpan isian lewat wizard v2 (`user.submissions.wizard.save`). Jawaban ditulis ke baris yang
// diresolusi dari form, periode, dan peneliti yang sama (ResolveWizardSubmission).

use App\Actions\FormSubmission\SaveSubmissionDraft;
use App\Actions\FormSubmission\StartSubmission;
use App\Data\Request\SubmissionAnswersInput;
use App\Models\AuditEntry;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @param  list<array{form_field_id: int, value: mixed}>  $responses */
function pagesSave(array $fx, FormSubmission $submission, array $responses): TestResponse
{
    return test()->actingAs($fx['user'])->post(
        route('user.submissions.wizard.save', [$submission, 2]),
        wizardPayload($submission->fresh()->updated_at->toISOString(), 3, $responses),
    );
}

/** @return array{0: array<string, mixed>, 1: FormSubmission, 2: FormField} */
function pagesFixture(): array
{
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    return [$fx, $submission, wizardTextField($fx['form'])];
}

it('menyimpan draft dengan satu entri audit', function () {
    [$fx, $submission, $field] = pagesFixture();

    pagesSave($fx, $submission, [['form_field_id' => $field->id, 'value' => 'Contoh nilai']])->assertRedirect();

    expect(FormFieldResponse::where('form_submission_id', $submission->id)->value('value'))->toBe('Contoh nilai')
        ->and(AuditEntry::query()->auditOnly()
            ->where('description', 'Draft pengajuan disimpan')
            ->where('subject_id', $submission->id)
            ->count())->toBe(1);
});

it('menimpa jawaban field yang diisi ulang', function () {
    [$fx, $submission, $field] = pagesFixture();

    pagesSave($fx, $submission, [['form_field_id' => $field->id, 'value' => 'Contoh nilai']]);
    pagesSave($fx, $submission, [['form_field_id' => $field->id, 'value' => 'Judul revisi']])->assertSessionHasNoErrors();

    expect(FormFieldResponse::where('form_submission_id', $submission->id)->pluck('value')->all())->toBe(['Judul revisi']);
});

it('menolak jawaban untuk field milik form lain', function () {
    [$fx, $submission, $field] = pagesFixture();
    $foreign = wizardTextField(Form::factory()->create());

    pagesSave($fx, $submission, [
        ['form_field_id' => $field->id, 'value' => 'Contoh nilai'],
        ['form_field_id' => $foreign->id, 'value' => 'Nyasar'],
    ])->assertSessionHasErrors('responses');

    expect(FormFieldResponse::where('form_submission_id', $submission->id)->count())->toBe(0);
});

it('membatasi ukuran berkas juga saat simpan draft', function () {
    Storage::fake('local');
    [$fx, $submission, $field] = pagesFixture();

    pagesSave($fx, $submission, [['form_field_id' => $field->id, 'value' => UploadedFile::fake()->create('proposal.pdf', 11 * 1024)]])
        ->assertSessionHasErrors('responses.0.value');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('tidak menyimpan berkas dari kiriman yang ditolak tanpa baris pengajuan', function () {
    // Wizard selalu membawa pengajuan di URL, jadi "belum ada baris" hanya tercapai lewat Action-nya.
    Storage::fake('local');
    $fx = wizardFixture();
    $field = FormField::create([
        'form_id' => $fx['form']->id,
        'label' => 'Proposal PDF',
        'is_required' => false,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'file'])->id,
        'order' => 1,
    ]);
    $input = new SubmissionAnswersInput(
        formId: $fx['form']->id,
        submissionPeriodId: $fx['period']->id,
        answers: [],
        uploads: [$field->id => UploadedFile::fake()->create('proposal.pdf', 10)],
        lastUpdatedAt: null,
    );

    expect(fn () => SaveSubmissionDraft::run($input, $fx['user']))->toThrow(ConflictHttpException::class)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('menyimpan berkas unggahan wizard sebagai jawaban field-nya', function () {
    // hasFile('responses') hanya memeriksa elemen tingkat pertama, sedangkan berkas
    // dikirim di responses.*.value — dulu setiap unggahan wizard hilang diam-diam.
    Storage::fake('local');
    [$fx, $submission, $field] = pagesFixture();

    pagesSave($fx, $submission, [['form_field_id' => $field->id, 'value' => UploadedFile::fake()->create('proposal.pdf', 10)]])
        ->assertSessionHasNoErrors();

    $path = FormFieldResponse::where('form_submission_id', $submission->id)->where('form_field_id', $field->id)->value('value');

    expect($path)->toStartWith('form-uploads/');
    Storage::disk('local')->assertExists($path);
});

it('tidak lagi mendaftarkan endpoint yang tidak dipanggil layar mana pun', function () {
    expect(Route::has('submissions.index'))->toBeFalse()
        ->and(Route::has('user.form-submission.data'))->toBeFalse()
        ->and(Route::has('user.form-submission.save-draft'))->toBeFalse()
        ->and(Route::has('user.form-submission.submit'))->toBeFalse();
});
