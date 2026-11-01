<?php

// tests/Feature/Submission/ResearcherWizardPagesTest.php
//
// Simpan draft lewat jalur legacy. Pengajuan yang ditulis adalah baris yang
// juga ditulis wizard: form, periode, dan peneliti yang sama (ResolveWizardSubmission).

use App\Actions\FormSubmission\StartSubmission;
use App\Models\AuditEntry;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

it('[SM-WIZARD-02] simpan dari halaman yang dibuka ulang tidak menghapus isian tersimpan', function () {
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $first = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->updated_at->toISOString());
    $judul = $first['responses'][0]['form_field_id'];

    $this->actingAs($fx['user'])->post(route('user.form-submission.save-draft'), $first)->assertSessionHasNoErrors();

    // Layar wizard mulai kosong setiap dibuka, jadi Judul terkirim '' sementara field lain diisi.
    $second = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->fresh()->updated_at->toISOString());
    $abstrak = $second['responses'][0]['form_field_id'];
    $second['responses'][] = ['form_field_id' => $judul, 'value' => ''];
    $second['responses'][0]['value'] = 'Abstrak baru';

    $this->actingAs($fx['user'])->post(route('user.form-submission.save-draft'), $second)->assertSessionHasNoErrors();

    $answers = FormFieldResponse::where('form_submission_id', $submission->id)->pluck('value', 'form_field_id');

    expect($answers->all())->toBe([$judul => 'Contoh nilai', $abstrak => 'Abstrak baru']);
});

it('menimpa jawaban field yang diisi ulang', function () {
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $payload = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->updated_at->toISOString());

    $this->actingAs($fx['user'])->post(route('user.form-submission.save-draft'), $payload);
    $payload['responses'][0]['value'] = 'Judul revisi';
    $payload['last_updated_at'] = $submission->fresh()->updated_at->toISOString();
    $this->actingAs($fx['user'])->post(route('user.form-submission.save-draft'), $payload)->assertSessionHasNoErrors();

    expect(FormFieldResponse::where('form_submission_id', $submission->id)->pluck('value')->all())->toBe(['Judul revisi']);
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
    Storage::fake('local');
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $payload = draftPayload($fx['form'], $fx['period'], $fx['phase'], $submission->updated_at->toISOString());
    $payload['responses'][0]['value'] = UploadedFile::fake()->create('proposal.pdf', 11 * 1024);

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), $payload)
        ->assertSessionHasErrors('responses.0.value');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('tidak menyimpan berkas dari kiriman yang ditolak tanpa baris pengajuan', function () {
    Storage::fake('local');
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

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('menyimpan berkas unggahan wizard sebagai jawaban field-nya', function () {
    // hasFile('responses') hanya memeriksa elemen tingkat pertama, sedangkan berkas
    // dikirim di responses.*.value — dulu setiap unggahan wizard hilang diam-diam.
    Storage::fake('local');
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
    Storage::disk('local')->assertExists($path);
});

it('tidak lagi mendaftarkan endpoint yang tidak dipanggil layar mana pun', function () {
    expect(Route::has('submissions.index'))->toBeFalse()
        ->and(Route::has('user.form-submission.data'))->toBeFalse();
});
