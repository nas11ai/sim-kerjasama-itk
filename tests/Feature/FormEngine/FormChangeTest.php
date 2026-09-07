<?php

// tests/Feature/FormEngine/FormChangeTest.php
//
// BR-FE-12, BR-FE-13 — mengubah Form yang draftnya sedang berjalan.
//
// Dua janji yang berbeda. BR-FE-12 menjaga **datanya**: field yang dihapus
// tidak membawa serta jawaban yang sudah terlanjur ditulis. BR-FE-13 menjaga
// **orangnya**: Admin tahu berapa yang terdampak sebelum menyebarkan
// perubahan, dan peneliti bisa diberi tahu.

use App\Actions\Form\NotifyFormChange;
use App\Actions\FormSubmission\StartSubmission;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\FormChangedNotification;
use Illuminate\Support\Facades\Notification;

it('[FE-CHANGE-01] menghapus field menyisakan jawaban lama', function () {
    $fx = wizardFixture();

    $field = FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'label' => 'Lampiran Lama',
        'is_required' => false,
        'order' => 1,
    ]);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $response = FormFieldResponse::create([
        'form_submission_id' => $submission->id,
        'form_field_id' => $field->id,
        'value' => 'Isi lampiran lama',
    ]);

    $field->delete();

    // Ditandai terhapus, bukan dihilangkan dari basis data.
    expect(FormField::find($field->id))->toBeNull()
        ->and(FormField::withTrashed()->find($field->id))->not->toBeNull()
        ->and(FormField::withTrashed()->find($field->id)->deleted_at)->not->toBeNull();

    // Jawaban lama tetap terbaca — inilah alasan penghapusannya lunak.
    expect(FormFieldResponse::find($response->id)?->value)->toBe('Isi lampiran lama');
});

it('[FE-CHANGE-02] admin diberi tahu jumlah draft terdampak', function () {
    Notification::fake();

    $fx = wizardFixture();

    // Tiga draft berjalan pada Form ini.
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    foreach (range(1, 2) as $i) {
        FormSubmission::factory()->create([
            'form_id' => $fx['form']->id,
            'submitted_by' => User::factory()->create()->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_detail_id' => $fx['detail']->id,
            'is_submitted' => false,
        ]);
    }

    // notify = false: Admin melihat angkanya lebih dulu, belum mengirim apa pun.
    $terdampak = NotifyFormChange::run($fx['form'], false);

    expect($terdampak)->toBe(3);
    Notification::assertNothingSent();
});

it('[FE-CHANGE-03] admin memilih mengirim notifikasi', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $terdampak = NotifyFormChange::run($fx['form'], true);

    expect($terdampak)->toBe(1);
    Notification::assertSentTo($fx['user'], FormChangedNotification::class);
});

it('[FE-CHANGE-04] tanpa draft berjalan tidak ada peringatan', function () {
    Notification::fake();

    $fx = wizardFixture();

    expect(NotifyFormChange::run($fx['form'], true))->toBe(0);
    Notification::assertNothingSent();
});

it('[FE-CHANGE-04] pengajuan yang sudah dikirim bukan draft terdampak', function () {
    Notification::fake();

    $fx = wizardFixture();

    FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'is_submitted' => true,
    ]);

    // Yang sudah dikirim tidak lagi diisi, jadi perubahan formulir tidak
    // mengubah apa pun baginya.
    expect(NotifyFormChange::run($fx['form'], true))->toBe(0);
    Notification::assertNothingSent();
});
