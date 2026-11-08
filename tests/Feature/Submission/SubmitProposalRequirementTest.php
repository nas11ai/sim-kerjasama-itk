<?php

// tests/Feature/Submission/SubmitProposalRequirementTest.php
//
// BR-SM-02 — proposal PDF wajib ada sebelum submit.

use App\Actions\FormSubmission\StartSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission, 2: FormField} */
function proposalSliceFixture(): array
{
    $owner = verifiedResearcher();
    $form = Form::factory()->create();
    // field_types.name unik — firstOrCreate supaya fixture aman dipakai ulang.
    $fileType = FieldType::firstOrCreate(['name' => 'file']);
    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'field_type_id' => $fileType->id,
        'order' => 1,
    ]);
    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => null,
    ]);

    return [$owner, $submission, $field];
}

it('[SM-SUBMIT-07] submit tanpa proposal PDF ditolak', function () {
    [$owner, $submission, $field] = proposalSliceFixture();

    try {
        SubmitFormSubmission::run($submission, $owner);
        $this->fail('Submit seharusnya ditolak karena proposal PDF belum diunggah.');
    } catch (ValidationException $e) {
        // Error harus per field, dan menyebut label yang dilihat peneliti.
        expect($e->errors())->toHaveKey('field_'.$field->id)
            ->and($e->errors()['field_'.$field->id][0])->toContain('Proposal PDF');
    }

    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-08] submit dengan proposal PDF lolos', function () {
    [$owner, $submission, $field] = proposalSliceFixture();
    $submission->formFieldResponses()->create([
        'form_field_id' => $field->id,
        'value' => 'form-uploads/proposal.pdf',
    ]);

    SubmitFormSubmission::run($submission->fresh(), $owner);

    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class);
});

// Spec §2 memetakan `melihat error per field pada "<field>"` ke lapis HTTP:
// bentuk error validasi Laravel hanya ada di respons. Satu skenario memang bisa
// menuntut dua test — yang di atas membuktikan aturannya, yang ini membuktikan
// aturan itu benar-benar terlihat di jalur yang dipakai peneliti.
it('[SM-SUBMIT-07] error per field pada Proposal PDF muncul di jalur submit wizard', function () {
    [$budi, $organization] = actingResearcher();

    $form = Form::factory()->create();
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $formPhase = FormPhase::create(['title' => 'Fase Pengajuan', 'is_active' => true]);
    $dibangun = buildAccessibleFormPhaseDetail($budi, $organization, $accessControl, $formPhase);

    // field_types.name unik — firstOrCreate supaya aman dipakai bersama fixture
    // di berkas yang sama.
    $fileType = FieldType::firstOrCreate(['name' => 'file']);
    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'field_type_id' => $fileType->id,
        'order' => 1,
    ]);

    // #28 — submit tidak membuat baris; langkah 1 wizard sudah melahirkannya.
    StartSubmission::run($form->id, $dibangun['period']->id, $dibangun['detail'], $budi);

    // Field `file` wajib dikirim tanpa berkas sama sekali.
    $pengajuan = FormSubmission::where('form_id', $form->id)->sole();

    $respons = $this->actingAs($budi)->post(route('user.submissions.submit', $pengajuan));

    $respons->assertRedirect()
        ->assertSessionHasErrors('field_'.$field->id);

    $pesan = session('errors')->get('field_'.$field->id);

    expect($pesan[0])->toContain('Proposal PDF');

    // Dan submit-nya memang tidak jadi.
    expect(FormSubmission::where('form_id', $form->id)->where('is_submitted', true)->exists())
        ->toBeFalse();
});
