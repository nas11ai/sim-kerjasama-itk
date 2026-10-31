<?php

// tests/Feature/Submission/SaveWizardStepTest.php
//
// Satu POST per perpindahan langkah: jawaban langkah itu tersimpan (bila ada), kemajuan
// dicatat, lalu wizard dibuka di langkah tujuan. Wizard v2 memuat ulang jawaban, jadi
// field yang dikosongkan benar-benar terhapus — kecuali berkas yang tidak dikirim ulang.

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\VerificationStatus;
use App\Models\AuditEntry;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{owner: User, submission: FormSubmission, fields: array<string, FormField>} */
function wizardSaveFixture(): array
{
    $fx = wizardFixture();
    $fields = [];

    foreach ([['Judul', 'text', true], ['Abstrak', 'textarea', false], ['Skema', 'scheme_selector', false], ['Proposal', 'file', true]] as $order => [$label, $type, $required]) {
        $fields[$label] = FormField::create([
            'form_id' => $fx['form']->id,
            'field_type_id' => FieldType::firstOrCreate(['name' => $type])->id,
            'label' => $label,
            'is_required' => $required,
            'order' => $order + 1,
        ]);
    }

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    return ['owner' => $fx['user'], 'submission' => $submission, 'fields' => $fields];
}

/** @param  list<array{form_field_id: int, value: mixed}>  $responses */
function wizardSave(User $user, FormSubmission $submission, int $step, int $goto, array $responses = [], ?string $lastUpdatedAt = null): TestResponse
{
    return test()->actingAs($user)->post(route('user.submissions.wizard.save', [$submission, $step]), [
        'goto' => $goto,
        'last_updated_at' => $lastUpdatedAt ?? $submission->fresh()->updated_at->toISOString(),
        'responses' => $responses,
    ]);
}

it('[SM-WIZARD-02] isian langkah 2 tersimpan dan dimuat ulang', function () {
    ['owner' => $owner, 'submission' => $submission, 'fields' => $fields] = wizardSaveFixture();

    wizardSave($owner, $submission, 2, 3, [['form_field_id' => $fields['Judul']->id, 'value' => 'Judul A']])
        ->assertRedirect(route('user.submissions.wizard', [$submission, 3]));

    $this->actingAs($owner)
        ->get(route('user.submissions.wizard', [$submission, 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fields.0.label', 'Judul')
            ->where('fields.0.value', 'Judul A'));
});

it('[SM-WIZARD-03] menyimpan langkah 2 tidak menuntut field langkah lain', function () {
    ['owner' => $owner, 'submission' => $submission, 'fields' => $fields] = wizardSaveFixture();

    wizardSave($owner, $submission, 2, 3, [['form_field_id' => $fields['Abstrak']->id, 'value' => 'Ringkas']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user.submissions.wizard', [$submission, 3]));
});

it('jawaban yang dikosongkan terhapus, berkas yang tidak dikirim tetap', function () {
    ['owner' => $owner, 'submission' => $submission, 'fields' => $fields] = wizardSaveFixture();
    FormFieldResponse::create(['form_submission_id' => $submission->id, 'form_field_id' => $fields['Abstrak']->id, 'value' => 'lama']);
    FormFieldResponse::create(['form_submission_id' => $submission->id, 'form_field_id' => $fields['Proposal']->id, 'value' => 'form-uploads/proposal.pdf']);

    wizardSave($owner, $submission, 2, 3, [
        ['form_field_id' => $fields['Judul']->id, 'value' => 'Judul B'],
        ['form_field_id' => $fields['Abstrak']->id, 'value' => ''],
    ])->assertSessionHasNoErrors();

    $values = $submission->formFieldResponses()->pluck('value', 'form_field_id');

    expect($values->has($fields['Abstrak']->id))->toBeFalse()
        ->and($values[$fields['Judul']->id])->toBe('Judul B')
        ->and($values[$fields['Proposal']->id])->toBe('form-uploads/proposal.pdf');
});

it('[SM-WIZARD-10] melompat mundur dari 6 ke 2 tidak mengubah kemajuan', function () {
    ['owner' => $owner, 'submission' => $submission] = wizardSaveFixture();
    $submission->update(['last_completed_step' => 6]);

    wizardSave($owner, $submission, 6, 2)->assertRedirect(route('user.submissions.wizard', [$submission, 2]));

    expect($submission->fresh()->last_completed_step)->toBe(6);
});

it('maju dari langkah 3 mencatat kemajuan 3', function () {
    ['owner' => $owner, 'submission' => $submission] = wizardSaveFixture();

    wizardSave($owner, $submission, 3, 4);
    wizardSave($owner, $submission, 3, 4);

    // BR-RPT-09 — dicatat sekali, saat kemajuannya bertambah.
    expect($submission->fresh()->last_completed_step)->toBe(3)
        ->and(AuditEntry::where('subject_id', $submission->id)->where('description', 'Langkah wizard diselesaikan')->count())->toBe(1);
});

it('berpindah langkah tanpa jawaban tidak mengubah updated_at', function () {
    ['owner' => $owner, 'submission' => $submission] = wizardSaveFixture();
    $before = $submission->fresh()->updated_at->toISOString();

    $this->travel(1)->hours();
    wizardSave($owner, $submission, 2, 3)->assertRedirect();

    $fresh = $submission->fresh();

    expect($fresh->updated_at->toISOString())->toBe($before)
        ->and($fresh->last_completed_step)->toBe(2);
});

it('[SM-WIZARD-05] memilih skema di langkah 3 menulis scheme_id', function () {
    ['owner' => $owner, 'submission' => $submission, 'fields' => $fields] = wizardSaveFixture();
    $scheme = Scheme::factory()->create(['is_active' => true]);

    wizardSave($owner, $submission, 3, 4, [['form_field_id' => $fields['Skema']->id, 'value' => (string) $scheme->id]])
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->scheme_id)->toBe($scheme->id);
});

it('[BR-FE-14] cap waktu basi ditolak 409', function () {
    ['owner' => $owner, 'submission' => $submission, 'fields' => $fields] = wizardSaveFixture();
    $stale = $submission->fresh()->updated_at->toISOString();
    $this->travel(1)->minutes();
    $submission->touch();

    wizardSave($owner, $submission, 2, 3, [['form_field_id' => $fields['Judul']->id, 'value' => 'Tabrakan']], $stale)
        ->assertStatus(409);
});

it('anggota tim ditolak 403', function () {
    ['submission' => $submission, 'fields' => $fields] = wizardSaveFixture();
    $member = verifiedResearcher();
    ResearchMember::factory()->create(['form_submission_id' => $submission->id, 'user_profile_id' => $member->userProfile->id]);

    wizardSave($member, $submission, 2, 3, [['form_field_id' => $fields['Judul']->id, 'value' => 'Bukan ketua']])
        ->assertForbidden();
});

it('[IAM-VERIFY-11] akun pending tetap boleh menyimpan', function () {
    ['owner' => $owner, 'submission' => $submission, 'fields' => $fields] = wizardSaveFixture();
    $owner->userProfile->update(['verification_status' => VerificationStatus::Pending->value]);

    wizardSave($owner, $submission, 2, 3, [['form_field_id' => $fields['Judul']->id, 'value' => 'Draft pending']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user.submissions.wizard', [$submission, 3]));

    expect($submission->formFieldResponses()->where('value', 'Draft pending')->exists())->toBeTrue();
});
