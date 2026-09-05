<?php

// tests/Unit/RequiredFieldGuardTest.php
//
// BR-FE-11 — Temporal Field Binding. Sebuah field hanya diwajibkan untuk sebuah
// submission kalau ia sudah ada DAN sudah wajib sebelum submission itu dibuat.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\States\Submission\Draft;
use App\Support\RequiredFieldGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Field dengan waktu lahir dan waktu-jadi-wajib yang dikendalikan penuh.
 * `booted()` di FormField menyetel required_since otomatis, jadi nilainya
 * ditimpa setelah pembuatan supaya skenario bisa menentukan tanggalnya.
 */
function temporalField(Form $form, string $label, bool $required, string $createdAt, ?string $requiredSince): FormField
{
    $type = FieldType::firstOrCreate(['name' => 'text']);

    $field = FormField::create([
        'form_id' => $form->id,
        'label' => $label,
        'is_required' => $required,
        'field_type_id' => $type->id,
        'order' => 1,
    ]);

    $field->forceFill([
        'created_at' => Carbon::parse($createdAt),
        'required_since' => $requiredSince === null ? null : Carbon::parse($requiredSince),
    ])->save();

    return $field->fresh();
}

function submissionCreatedAt(Form $form, string $createdAt): FormSubmission
{
    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'status' => Draft::class,
        'scheme_id' => null,
    ]);

    $submission->forceFill(['created_at' => Carbon::parse($createdAt)])->save();

    return $submission->fresh();
}

it('[FE-TEMPORAL-01] field wajib yang lahir sebelum submission tetap diwajibkan', function () {
    $form = Form::factory()->create();
    $field = temporalField($form, 'Abstrak', true, '2026-01-10 00:00', '2026-01-10 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);

    try {
        RequiredFieldGuard::assertComplete($submission);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('field_'.$field->id)
            ->and($e->errors()['field_'.$field->id][0])->toContain('Abstrak');
    }
});

it('[FE-TEMPORAL-02] field yang lahir setelah submission tidak diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Roadmap', true, '2026-03-01 00:00', '2026-03-01 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('[FE-TEMPORAL-03] field yang baru jadi wajib setelah submission tidak diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Mitra', true, '2026-01-10 00:00', '2026-03-01 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('[FE-TEMPORAL-04] field yang jadi wajib tepat sebelum submission diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Mitra', true, '2026-01-10 00:00', '2026-01-20 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});

it('[FE-TEMPORAL-05] required_since kosong berarti mengikuti is_required apa adanya', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Judul', true, '2026-01-10 00:00', null);
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});

it('[FE-TEMPORAL-06] field opsional tidak pernah diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Catatan', false, '2026-01-10 00:00', null);
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('[FE-TEMPORAL-07] field yang lahir tepat bersamaan dengan submission diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Luaran', true, '2026-02-01 09:00', '2026-02-01 09:00');
    $submission = submissionCreatedAt($form, '2026-02-01 09:00');

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});

it('[FE-TEMPORAL-08] field yang sudah di-soft-delete tidak diwajibkan', function () {
    $form = Form::factory()->create();
    $field = temporalField($form, 'Lampiran Lama', true, '2026-01-10 00:00', '2026-01-10 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    $field->delete();

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('field wajib yang sudah terisi tidak memblokir', function () {
    $form = Form::factory()->create();
    $field = temporalField($form, 'Abstrak', true, '2026-01-10 00:00', '2026-01-10 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    $submission->formFieldResponses()->create([
        'form_field_id' => $field->id,
        'value' => 'Isi abstrak',
    ]);

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('response kosong dihitung belum terisi', function () {
    $form = Form::factory()->create();
    $field = temporalField($form, 'Abstrak', true, '2026-01-10 00:00', '2026-01-10 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    $submission->formFieldResponses()->create([
        'form_field_id' => $field->id,
        'value' => '',
    ]);

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});
