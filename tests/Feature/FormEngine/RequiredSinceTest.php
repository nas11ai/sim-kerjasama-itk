<?php

// tests/Feature/FormEngine/RequiredSinceTest.php
//
// BR-FE-18 — `required_since` disetel saat aturannya berubah, tidak pernah
// mundur.
//
// Aturan ini semula hanya prosa di 01_form_engine.md dan tidak pernah
// dinomori; ia dinomori saat korpus BDD dilengkapi, karena seluruh jaminan
// BR-FE-11 bergantung padanya. Menyetel required_since mundur membuat draft
// yang sudah berjalan tiba-tiba punya field wajib yang belum pernah dilihat
// pengisinya.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\RequiredFieldGuard;
use Illuminate\Validation\ValidationException;

it('[FE-CHANGE-05] field yang baru diwajibkan berlaku sejak saat itu', function () {
    $form = Form::factory()->create();
    $type = FieldType::firstOrCreate(['name' => 'text']);

    $field = FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $type->id,
        'label' => 'Mitra',
        'is_required' => false,
        'order' => 1,
    ]);

    $field->update(['is_required' => true, 'required_since' => now()]);

    // Berlaku sejak diubah, bukan sejak field itu lahir.
    expect($field->fresh()->required_since)->not->toBeNull()
        ->and($field->fresh()->required_since->greaterThan($field->created_at->subSecond()))->toBeTrue();
});

it('[FE-CHANGE-06] draft berjalan tidak ikut terkena field wajib baru', function () {
    $form = Form::factory()->create();
    $type = FieldType::firstOrCreate(['name' => 'text']);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
    ]);
    $submission->forceFill(['created_at' => now()->subMonth()])->save();

    // Field diwajibkan SETELAH pengajuan ini dibuat.
    FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $type->id,
        'label' => 'Mitra',
        'is_required' => true,
        'required_since' => now(),
        'order' => 1,
    ]);

    // Pengajuan yang sudah berjalan tidak ditarik ikut — itu inti BR-FE-11.
    RequiredFieldGuard::assertComplete($submission->fresh());

    expect(true)->toBeTrue();
});

it('[FE-CHANGE-06] pengajuan baru justru terkena field wajib itu', function () {
    $form = Form::factory()->create();
    $type = FieldType::firstOrCreate(['name' => 'text']);

    $field = FormField::create([
        'form_id' => $form->id,
        'field_type_id' => $type->id,
        'label' => 'Mitra',
        'is_required' => true,
        'order' => 1,
    ]);
    $field->forceFill(['required_since' => now()->subMonth(), 'created_at' => now()->subMonth()])->save();

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
    ]);

    // Pasangan positifnya: kalau ini lolos, aturannya tidak menyala sama sekali.
    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});
