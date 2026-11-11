<?php

// tests/Feature/Submission/SubmissionWizardQueryTest.php
//
// Read model wizard pengajuan (RESEARCHER-02-02..02-10). Field Form Engine dibagi
// ke langkah menurut tipenya, karena Form Engine tidak menyimpan langkah per field.

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\DeadlineType;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\TechnologyReadinessLevel;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Submission\ResearcherStartOptions;
use App\Queries\Submission\SubmissionWizard;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use Illuminate\Support\Facades\DB;

function wizardQueryField(Form $form, string $type, string $label, int $order, bool $required = false): FormField
{
    return FormField::create([
        'form_id' => $form->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => $type])->id,
        'label' => $label,
        'is_required' => $required,
        'order' => $order,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function wizardQueryDraft(Form $form, User $owner, array $attributes = []): FormSubmission
{
    return FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        ...$attributes,
    ]);
}

function wizardQueryPhaseInPeriod(int $periodId, int $phaseId): void
{
    // Idempoten: wizardFixture sudah menautkan fase ke periodenya sendiri (R1-W2).
    DB::table('submission_period_phases')->updateOrInsert(['submission_period_id' => $periodId, 'form_phase_id' => $phaseId]);
}

/** @return list<string> */
function wizardQueryLabels(array $fields): array
{
    return array_map(fn ($field) => $field->label, $fields);
}

it('membagi field per langkah menurut tipe', function () {
    $owner = verifiedResearcher();
    $form = Form::factory()->create();
    wizardQueryField($form, 'text', 'Judul', 1);
    wizardQueryField($form, 'scheme_selector', 'Skema', 2);
    wizardQueryField($form, 'file', 'Proposal PDF', 3);
    $submission = wizardQueryDraft($form, $owner);

    expect(wizardQueryLabels(SubmissionWizard::run($owner, $submission, 2)['fields']))->toBe(['Judul'])
        ->and(wizardQueryLabels(SubmissionWizard::run($owner, $submission, 3)['fields']))->toBe(['Skema'])
        ->and(wizardQueryLabels(SubmissionWizard::run($owner, $submission, 6)['fields']))->toBe(['Proposal PDF'])
        ->and(SubmissionWizard::run($owner, $submission, 7)['fields'])->toBe([]);
});

it('[FE-TEMPORAL-01] field yang diwajibkan setelah draft dibuat tidak wajib', function () {
    $owner = verifiedResearcher();
    $form = Form::factory()->create();
    $lama = wizardQueryField($form, 'text', 'Judul', 1, required: true);
    $baru = wizardQueryField($form, 'text', 'Abstrak', 2);
    $submission = wizardQueryDraft($form, $owner);

    $this->travel(1)->days();
    $baru->update(['is_required' => true]);

    $fields = collect(SubmissionWizard::run($owner, $submission->fresh(), 2)['fields'])->keyBy('id');

    expect($fields[$lama->id]->required)->toBeTrue()
        ->and($fields[$baru->id]->required)->toBeFalse();
});

it('[SCH-RULE-14] skema nonaktif tidak ditawarkan kecuali sudah terpilih', function () {
    $owner = verifiedResearcher();
    $form = Form::factory()->create();
    $field = wizardQueryField($form, 'scheme_selector', 'Skema', 1);
    $aktif = Scheme::factory()->create(['is_active' => true]);
    $mati = Scheme::factory()->create(['is_active' => false]);
    $terpilih = Scheme::factory()->create(['is_active' => false]);
    $submission = wizardQueryDraft($form, $owner, ['scheme_id' => $terpilih->id]);
    FormFieldResponse::create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => (string) $terpilih->id]);

    $schemeField = SubmissionWizard::run($owner, $submission, 3)['fields'][0];
    $values = array_column($schemeField->options, 'value');

    expect($values)->toContain((string) $aktif->id, (string) $terpilih->id)
        ->not->toContain((string) $mati->id)
        ->and($schemeField->value)->toBe((string) $terpilih->id);
});

it('[SCH-RULE-10] TRL tersaring skema terpilih', function () {
    $owner = verifiedResearcher();
    $form = Form::factory()->create();
    wizardQueryField($form, 'trl_selector', 'TRL', 1);
    $trls = collect(range(1, 3))->map(fn (int $level) => TechnologyReadinessLevel::create(['level' => $level, 'name' => "Tingkat {$level}"]));
    $scheme = Scheme::factory()->create();
    $scheme->allowedTrls()->attach([$trls[0]->id, $trls[1]->id]);
    $submission = wizardQueryDraft($form, $owner, ['scheme_id' => $scheme->id]);

    $options = SubmissionWizard::run($owner, $submission, 3)['fields'][0]->options;

    expect(array_column($options, 'value'))->toBe([(string) $trls[0]->id, (string) $trls[1]->id])
        ->and($options[0]['label'])->toBe('TRL 1 — Tingkat 1');
});

it('TRL kosong tanpa skema', function () {
    $owner = verifiedResearcher();
    $form = Form::factory()->create();
    wizardQueryField($form, 'trl_selector', 'TRL', 1);
    $submission = wizardQueryDraft($form, $owner, ['scheme_id' => null]);

    expect(SubmissionWizard::run($owner, $submission, 3)['fields'][0]->options)->toBe([]);
});

it('[SM-WIZARD-04] langkah 5 membawa alasan penguncian', function () {
    $owner = verifiedResearcher();
    $form = Form::factory()->create();
    wizardQueryField($form, 'scheme_selector', 'Skema', 1);
    $submission = wizardQueryDraft($form, $owner, ['scheme_id' => null, 'last_completed_step' => 3]);

    $steps = SubmissionWizard::run($owner, $submission, 5)['steps'];

    expect($steps)->toHaveCount(7)
        ->and($steps[4]->blockedReason)->toContain('Pilih skema di langkah 3')
        ->and($steps[1]->blockedReason)->toBeNull()
        ->and($steps[2]->completed)->toBeTrue()
        ->and($steps[3]->completed)->toBeFalse()
        ->and($steps[0]->label)->toBe('Pilih Form & Periode');
});

it('[SM-WIZARD-06] langkah 7 mendaftar field wajib yang kosong beserta langkahnya', function () {
    $owner = verifiedResearcher();
    $form = Form::factory()->create();
    $judul = wizardQueryField($form, 'text', 'Judul', 1, required: true);
    $proposal = wizardQueryField($form, 'file', 'Proposal PDF', 2, required: true);
    wizardQueryField($form, 'text', 'Catatan', 3);
    $submission = wizardQueryDraft($form, $owner);

    $review = SubmissionWizard::run($owner, $submission, 7)['review'];
    $missing = collect($review->missingFields)->keyBy('fieldId');

    expect($missing)->toHaveCount(2)
        ->and($missing[$judul->id]->step)->toBe(2)
        ->and($missing[$proposal->id]->step)->toBe(6)
        ->and($missing[$proposal->id]->message)->toBe("Field 'Proposal PDF' wajib diisi sebelum submit.")
        ->and($review->accountBlock)->toBeNull()
        ->and(SubmissionWizard::run($owner, $submission, 2)['review'])->toBeNull();
});

it('[IAM-VERIFY-02] [IAM-VERIFY-09] langkah 7 membawa alasan akun', function (string $state, string $message) {
    $owner = User::factory()->create();
    UserProfile::factory()->{$state}()->create(['user_id' => $owner->id]);
    $submission = wizardQueryDraft(Form::factory()->create(), $owner->refresh());

    expect(SubmissionWizard::run($owner, $submission, 7)['review']->accountBlock)->toContain($message);
})->with([
    'pending' => ['pending', 'Akun Anda belum terverifikasi.'],
    'ditolak' => ['rejected', 'Verifikasi NIDN Anda ditolak.'],
]);

it('opsi mulai hanya dari periode terbuka dan form yang boleh diakses', function () {
    $fx = wizardFixture();
    wizardQueryPhaseInPeriod($fx['period']->id, $fx['phase']->id);
    $tutup = SubmissionPeriod::factory()->create(['is_force_closed' => true]);
    wizardQueryPhaseInPeriod($tutup->id, $fx['phase']->id);
    // Form di fase yang sama tapi dibuka untuk organisasi lain.
    $asing = FormAccessControl::create([
        'form_id' => Form::factory()->create()->id,
        'permission' => 'submissions.create',
        'organization_id' => Organization::create(['name' => 'Org Lain', 'type' => 'study_program'])->id,
    ]);
    $fx['detail']->replicate()->fill(['form_access_control_id' => $asing->id, 'order' => 2])->save();

    $options = ResearcherStartOptions::run($fx['user']);

    expect($options)->toHaveCount(1)
        ->and($options[0]->id)->toBe($fx['period']->id)
        ->and($options[0]->closesAt)->not->toBeNull()
        ->and(array_map(fn ($form) => $form->formId, $options[0]->forms))->toBe([$fx['form']->id])
        ->and($options[0]->forms[0]->formPhaseId)->toBe($fx['phase']->id)
        ->and($options[0]->forms[0]->draftId)->toBeNull();
});

it('opsi mulai menautkan draft yang masih bisa disunting', function () {
    $fx = wizardFixture();
    wizardQueryPhaseInPeriod($fx['period']->id, $fx['phase']->id);
    $draft = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect(ResearcherStartOptions::run($fx['user'])[0]->forms[0]->draftId)->toBe($draft->id);

    $draft->update(['status' => Rejected::class, 'is_submitted' => true]);

    expect(ResearcherStartOptions::run($fx['user'])[0]->forms[0]->draftId)->toBeNull();
});

it('fase yang tidak terpasang di periode tidak ditawarkan', function () {
    // Pindahan dari layar tahap formulir legacy: tahap di luar periode tidak punya langkah.
    $fx = wizardFixture();
    $fx['phase']->submissionPeriodPhases()->delete();

    expect(ResearcherStartOptions::run($fx['user']))->toBe([]);
});

it('draft periode lain tidak ditautkan', function () {
    // Pindahan dari layar legacy: fase yang dipakai dua periode memuat draft periode itu saja.
    $fx = wizardFixture();
    $other = SubmissionPeriod::factory()->create();
    // BR-FE-01 (K8d) — periode tanpa Batas Submit belum menerima pengajuan, jadi tak ditawarkan.
    $other->submissionDates()->create(['type' => DeadlineType::Submit, 'datetime' => now()->addMonth()]);
    wizardQueryPhaseInPeriod($fx['period']->id, $fx['phase']->id);
    wizardQueryPhaseInPeriod($other->id, $fx['phase']->id);
    $draft = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $byPeriod = collect(ResearcherStartOptions::run($fx['user']))->keyBy('id');

    expect($byPeriod[$fx['period']->id]->forms[0]->draftId)->toBe($draft->id)
        ->and($byPeriod[$other->id]->forms[0]->draftId)->toBeNull();
});

it('dua tahap ber-Form pengajuan sama di satu periode: yang ditawarkan selalu tahap tertua', function () {
    // Pustaka fase (R2) membuat tahap mudah disalin; tanpa urutan, baris tautan menentukan pilihannya.
    $fx = wizardFixture();
    $copy = FormPhase::create(['title' => 'Fase Uji Salinan', 'is_active' => true]);
    $fx['detail']->replicate()->fill(['form_phase_id' => $copy->id])->save();
    $fx['phase']->submissionPeriodPhases()->delete();
    wizardQueryPhaseInPeriod($fx['period']->id, $copy->id);
    wizardQueryPhaseInPeriod($fx['period']->id, $fx['phase']->id);

    $forms = ResearcherStartOptions::run($fx['user'])[0]->forms;

    expect($forms)->toHaveCount(1)
        ->and($forms[0]->formPhaseId)->toBe($fx['phase']->id);
});
