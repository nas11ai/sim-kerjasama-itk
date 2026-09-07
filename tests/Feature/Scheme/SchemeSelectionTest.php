<?php

// tests/Feature/Scheme/SchemeSelectionTest.php
//
// BR-SCH-04, BR-SCH-05, BR-SCH-10 — skema sebagai pilihan di dalam wizard.
//
// Skema sepenuhnya opsional per Form. Yang tidak opsional adalah akibatnya:
// begitu sebuah Form punya scheme_selector, pilihannya harus mendarat di kolom
// scheme_id (ADR 0005), karena batas anggaran, anggota, dan tipe luaran
// semuanya menanyakan kolom itu.

use App\Actions\FormSubmission\StartSubmission;
use App\Actions\Scheme\SyncSchemeSelection;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\Scheme;
use App\Models\SchemeAllowedTrl;
use App\Models\TechnologyReadinessLevel;
use App\Support\SchemeFieldGuard;
use Illuminate\Validation\ValidationException;

function schemeSelectorField(int $formId): FormField
{
    return FormField::create([
        'form_id' => $formId,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'scheme_selector'])->id,
        'label' => 'Skema',
        'is_required' => true,
        'order' => 1,
    ]);
}

it('[SCH-RULE-07] Form tanpa scheme_selector berjalan tanpa skema', function () {
    $fx = wizardFixture();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect(SyncSchemeSelection::run($submission))->toBeNull()
        ->and($submission->fresh()->scheme_id)->toBeNull();
});

it('[SCH-RULE-08] Form dengan scheme_selector menulis ke kolom scheme_id', function () {
    $fx = wizardFixture();
    $field = schemeSelectorField($fx['form']->id);
    $scheme = Scheme::factory()->create(['is_active' => true]);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    FormFieldResponse::create([
        'form_submission_id' => $submission->id,
        'form_field_id' => $field->id,
        'value' => (string) $scheme->id,
    ]);

    expect(SyncSchemeSelection::run($submission)?->id)->toBe($scheme->id)
        ->and($submission->fresh()->scheme_id)->toBe($scheme->id);
});

it('[SCH-RULE-14] skema tidak aktif tidak bisa dipilih pengajuan baru', function () {
    $fx = wizardFixture();
    $field = schemeSelectorField($fx['form']->id);
    $mati = Scheme::factory()->create(['is_active' => false]);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    FormFieldResponse::create([
        'form_submission_id' => $submission->id,
        'form_field_id' => $field->id,
        'value' => (string) $mati->id,
    ]);

    expect(fn () => SyncSchemeSelection::run($submission))->toThrow(ValidationException::class)
        ->and($submission->fresh()->scheme_id)->toBeNull();
});

it('[SCH-RULE-14] pengajuan yang terlanjur memakai skema nonaktif tidak diusik', function () {
    $fx = wizardFixture();
    $field = schemeSelectorField($fx['form']->id);
    $scheme = Scheme::factory()->create(['is_active' => true]);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $submission->update(['scheme_id' => $scheme->id]);

    FormFieldResponse::create([
        'form_submission_id' => $submission->id,
        'form_field_id' => $field->id,
        'value' => (string) $scheme->id,
    ]);

    $scheme->update(['is_active' => false]);

    // Yang dilarang adalah *memilihnya sekarang*, bukan yang sudah berjalan.
    expect(SyncSchemeSelection::run($submission)?->id)->toBe($scheme->id);
});

it('[SCH-RULE-09] trl_selector tanpa scheme_selector ditolak', function () {
    $trl = FieldType::firstOrCreate(['name' => 'trl_selector']);

    expect(fn () => SchemeFieldGuard::assertTrlHasSchemeSelector([$trl->id]))
        ->toThrow(ValidationException::class);
});

it('[SCH-RULE-09] trl_selector diterima kalau scheme_selector ada di Form yang sama', function () {
    $trl = FieldType::firstOrCreate(['name' => 'trl_selector']);
    $scheme = FieldType::firstOrCreate(['name' => 'scheme_selector']);

    SchemeFieldGuard::assertTrlHasSchemeSelector([$trl->id, $scheme->id]);
})->throwsNoExceptions();

it('[SCH-RULE-10] pilihan TRL tersaring oleh skema terpilih', function () {
    $scheme = Scheme::factory()->create();

    $semua = collect(range(1, 5))->map(fn (int $level) => TechnologyReadinessLevel::create([
        'name' => "TRL {$level}",
        'level' => $level,
        'description' => "Tingkat kesiapan {$level}",
    ]));

    $diizinkan = $semua->take(3);

    foreach ($diizinkan as $trl) {
        SchemeAllowedTrl::create(['scheme_id' => $scheme->id, 'trl_id' => $trl->id]);
    }

    // Skema hanya menawarkan TRL yang ia izinkan — dua sisanya tidak ikut,
    // walaupun ada di taksonomi.
    expect($scheme->allowedTrls()->pluck('technology_readiness_levels.id')->sort()->values()->all())
        ->toBe($diizinkan->pluck('id')->sort()->values()->all())
        ->and($scheme->allowedTrls()->count())->toBe(3);
});
