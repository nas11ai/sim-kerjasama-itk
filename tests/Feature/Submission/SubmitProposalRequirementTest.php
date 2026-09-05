<?php

// tests/Feature/Submission/SubmitProposalRequirementTest.php
//
// BR-SM-02 — proposal PDF wajib ada sebelum submit.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission, 2: FormField} */
function proposalSliceFixture(): array
{
    $owner = User::factory()->create();
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
