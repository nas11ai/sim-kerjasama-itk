<?php

// tests/Feature/Submission/WizardNavigationTest.php
//
// BR-SM-17 — navigasi wizard bebas maju-mundur. Yang menggerbang adalah
// **prasyarat data per langkah**, bukan urutan langkah.
//
// Satu-satunya prasyarat nyata: langkah Anggaran butuh `scheme_id`, dan
// hanya pada Form yang memang punya `scheme_selector` — batas anggarannya
// berasal dari skema (BR-BUD-01). Form tanpa scheme_selector tidak punya
// prasyarat itu, jadi langkahnya terbuka.
//
// Langkah Review boleh dibuka kapan saja; yang bergerbang tombol Submit-nya,
// bukan navigasinya. Mengunci urutan akan menyiksa: memperbaiki judul di
// langkah 2 saat berada di langkah 6 tidak boleh menuntut mundur empat layar.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\User;
use App\States\Submission\Draft;
use App\Support\RequiredFieldGuard;
use App\Support\WizardStepGate;
use Illuminate\Validation\ValidationException;

/** @return array{0: FormSubmission, 1: Form} */
function wizardNavFixture(bool $withSchemeSelector = true, ?int $schemeId = null): array
{
    $form = Form::factory()->create();

    if ($withSchemeSelector) {
        $type = FieldType::firstOrCreate(['name' => 'scheme_selector']);
        FormField::create([
            'form_id' => $form->id,
            'field_type_id' => $type->id,
            'label' => 'Skema',
            'is_required' => true,
            'order' => 1,
        ]);
    }

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $schemeId,
        'status' => Draft::class,
        'last_completed_step' => 1,
    ]);

    return [$submission, $form];
}

it('[SM-WIZARD-11] langkah tanpa prasyarat terbuka kapan saja', function () {
    [$submission] = wizardNavFixture();

    // Langkah 2 (Informasi Dasar) dan 7 (Review) tidak punya prasyarat data,
    // jadi keduanya terbuka meski baru langkah 1 yang selesai.
    expect(WizardStepGate::isOpen($submission, 2))->toBeTrue()
        ->and(WizardStepGate::isOpen($submission, 7))->toBeTrue();
});

it('[SM-WIZARD-04] anggaran terkunci selama skema belum dipilih', function () {
    [$submission] = wizardNavFixture();

    expect(WizardStepGate::isOpen($submission, WizardStepGate::STEP_BUDGET))->toBeFalse();
});

it('[SM-WIZARD-04] anggaran terbuka setelah skema dipilih', function () {
    $scheme = Scheme::factory()->create();
    [$submission] = wizardNavFixture(schemeId: $scheme->id);

    expect(WizardStepGate::isOpen($submission, WizardStepGate::STEP_BUDGET))->toBeTrue();
});

it('[SM-WIZARD-09] Form tanpa scheme_selector melewati pembatas skema', function () {
    [$submission] = wizardNavFixture(withSchemeSelector: false);

    // Tidak punya skema, tapi juga tidak pernah menuntutnya — batas anggaran
    // memang tidak berlaku di sini.
    expect(WizardStepGate::isOpen($submission, WizardStepGate::STEP_BUDGET))->toBeTrue();
});

it('[SM-WIZARD-10] melompat mundur beberapa langkah sekaligus diperbolehkan', function () {
    [$submission] = wizardNavFixture();
    $submission->update(['last_completed_step' => 6]);

    expect(WizardStepGate::isOpen($submission, 2))->toBeTrue()
        ->and(WizardStepGate::isOpen($submission, 3))->toBeTrue();
});

it('[SM-WIZARD-11] melompat maju melewati langkah yang belum selesai diperbolehkan', function () {
    [$submission] = wizardNavFixture();

    // last_completed_step = 1, tapi langkah 6 tetap boleh dibuka: ia tidak
    // punya prasyarat data. Nomor langkah bukan gerbang.
    expect(WizardStepGate::isOpen($submission, 6))->toBeTrue();
});

it('[SM-WIZARD-07] draft dilanjutkan dari langkah terakhir yang selesai', function () {
    [$submission] = wizardNavFixture();
    $submission->update(['last_completed_step' => 4]);

    expect(WizardStepGate::resumeStep($submission->fresh()))->toBe(4);
});

it('[SM-WIZARD-07] draft yang baru mulai dilanjutkan dari langkah 1', function () {
    [$submission] = wizardNavFixture();

    expect(WizardStepGate::resumeStep($submission))->toBe(1);
});

it('[SM-WIZARD-04] alasan penguncian disebutkan, bukan sekadar ditolak', function () {
    [$submission] = wizardNavFixture();

    // Peneliti yang cuma melihat langkah terkunci tidak tahu harus berbuat
    // apa; alasannya menunjuk langkah yang harus diisi lebih dulu.
    expect(WizardStepGate::blockedReason($submission, WizardStepGate::STEP_BUDGET))
        ->toContain('skema');
});

it('[SM-WIZARD-11] langkah yang terbuka tidak punya alasan penguncian', function () {
    [$submission] = wizardNavFixture();

    expect(WizardStepGate::blockedReason($submission, 2))->toBeNull();
});

it('[SM-WIZARD-02] isian langkah sebelumnya bertahan saat berpindah', function () {
    [$submission, $form] = wizardNavFixture();

    $text = FieldType::firstOrCreate(['name' => 'text']);
    $judul = FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $text->id,
        'label' => 'Judul',
        'is_required' => false,
        'order' => 2,
    ]);

    $submission->formFieldResponses()->create([
        'form_field_id' => $judul->id,
        'value' => 'Analisis Biochar',
    ]);

    // Berpindah langkah tidak menyentuh jawaban yang sudah tersimpan.
    $submission->update(['last_completed_step' => 3]);

    expect($submission->fresh()->formFieldResponses()->where('form_field_id', $judul->id)->value('value'))
        ->toBe('Analisis Biochar');
});

it('[SM-WIZARD-05] langkah 3 menulis ke kolom, bukan ke jawaban form', function () {
    $scheme = Scheme::factory()->create();
    [$submission] = wizardNavFixture(schemeId: $scheme->id);

    // ADR 0005 — scheme_selector menulis ke form_submissions.scheme_id,
    // bukan ke form_field_responses. Menyimpannya sebagai EAV menggagalkan
    // integritas, BR-SM-01, dan performa query.
    expect($submission->scheme_id)->toBe($scheme->id)
        ->and($submission->formFieldResponses()->count())->toBe(0);
});

it('[SM-WIZARD-03] validasi langkah bersifat parsial', function () {
    [$submission, $form] = wizardNavFixture();

    $text = FieldType::firstOrCreate(['name' => 'text']);
    FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $text->id,
        'label' => 'Mitra',
        'is_required' => true,
        'required_since' => now()->subYear(),
        'order' => 3,
    ]);

    // Field wajib masih kosong, tapi langkah lain tetap boleh dibuka dan
    // disimpan — validasi penuh baru berjalan saat submit di langkah 7.
    expect(WizardStepGate::isOpen($submission, 2))->toBeTrue()
        ->and(WizardStepGate::isOpen($submission, WizardStepGate::STEP_REVIEW))->toBeTrue();
});

it('[SM-WIZARD-06] validasi penuh baru berjalan di langkah 7', function () {
    [$submission, $form] = wizardNavFixture();

    $text = FieldType::firstOrCreate(['name' => 'text']);
    $mitra = FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $text->id,
        'label' => 'Mitra',
        'is_required' => true,
        'required_since' => now()->subYear(),
        'order' => 3,
    ]);

    // Langkah 7 terbuka — yang bergerbang tombol Submit-nya.
    expect(WizardStepGate::isOpen($submission, WizardStepGate::STEP_REVIEW))->toBeTrue();

    // Dan di situlah validasi penuh menolak, menyebut field-nya.
    try {
        RequiredFieldGuard::assertComplete($submission);
        $this->fail('Kirim seharusnya ditolak: isian wajib masih kosong.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey("field_{$mitra->id}");
    }
});

it('[SM-WIZARD-08] submit ditolak selama prasyarat belum terpenuhi', function () {
    [$submission, $form] = wizardNavFixture();

    $file = FieldType::firstOrCreate(['name' => 'file']);
    FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $file->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'required_since' => now()->subYear(),
        'order' => 4,
    ]);

    // Berkas wajib belum diunggah — navigasi tetap bebas, submit yang ditahan.
    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});
