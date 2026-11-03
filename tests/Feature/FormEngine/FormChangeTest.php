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
use App\Models\FormFieldOption;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\FormChangedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/**
 * Admin yang benar-benar bisa membuka layar Form: grup rute Form dijaga
 * `can:schemes.manage` (W88), jadi role saja tidak cukup.
 */
function formAdmin(): User
{
    Permission::findOrCreate('schemes.manage', 'web');

    $admin = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $admin->id]);
    $admin->givePermissionTo('schemes.manage');

    return $admin->refresh();
}

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

// BR-FE-13 sampai ke layar. Sebelum irisan ini, `affectedDrafts` dan
// `notify_affected` tidak muncul sama sekali di frontend: aturannya lulus di
// lapis Action, tapi Admin tidak pernah benar-benar diperingatkan dan tidak
// pernah benar-benar ditawari mengirim notifikasi.

it('[FE-CHANGE-02] halaman edit Form membawa jumlah draft terdampak', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $admin = formAdmin();

    $this->actingAs($admin)
        ->get(route('admin.forms.edit', $fx['form']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FormEngine/FormEditPage')
            ->where('general.affectedDrafts', 1)
            ->etc());
});

it('[FE-CHANGE-03] menyimpan dengan notify_affected mengirim notifikasi', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $admin = formAdmin();

    $this->actingAs($admin)
        ->put(route('admin.forms.update', $fx['form']), [
            'title' => $fx['form']->title,
            'form_type_id' => $fx['form']->form_type_id,
            'fields' => [],
            'notify_affected' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Form diperbarui. 1 peneliti diberi tahu.');

    Notification::assertSentTo($fx['user'], FormChangedNotification::class);
});

it('[FE-CHANGE-03] tanpa centang, perubahan tersimpan tanpa notifikasi', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $admin = formAdmin();

    $this->actingAs($admin)
        ->put(route('admin.forms.update', $fx['form']), [
            'title' => 'Judul Baru',
            'form_type_id' => $fx['form']->form_type_id,
            'fields' => [],
            'notify_affected' => false,
        ])
        ->assertRedirect();

    // Pengiriman ditawarkan, bukan otomatis — Admin yang memutuskan.
    Notification::assertNothingSent();
    expect($fx['form']->fresh()->title)->toBe('Judul Baru');
});

// ---------- BR-FE-23: tipe field terkunci begitu ada jawaban ----------

/** @return array{field: FormField, submission: FormSubmission} */
function fieldTerjawab(string $tipe = 'text'): array
{
    $fx = wizardFixture();

    $field = FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => $tipe])->id,
        'label' => 'Ringkasan',
        'is_required' => false,
        'order' => 1,
    ]);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    return ['field' => $field, 'submission' => $submission];
}

it('[FE-CHANGE-07] tipe field tidak bisa diubah setelah ada jawaban', function () {
    ['field' => $field, 'submission' => $submission] = fieldTerjawab();

    foreach (range(1, 7) as $i) {
        FormFieldResponse::create([
            'form_submission_id' => FormSubmission::factory()->create()->id,
            'form_field_id' => $field->id,
            'value' => "Isi {$i}",
        ]);
    }

    $select = FieldType::firstOrCreate(['name' => 'select'])->id;

    expect(fn () => $field->update(['field_type_id' => $select]))
        ->toThrow(ValidationException::class)
        ->and($field->fresh()->field_type_id)->not->toBe($select)
        ->and(FormFieldResponse::where('form_field_id', $field->id)->count())->toBe(7);
});

it('[FE-CHANGE-08] tipe field bebas diubah selama belum ada jawaban', function () {
    ['field' => $field] = fieldTerjawab();

    $textarea = FieldType::firstOrCreate(['name' => 'textarea'])->id;
    $field->update(['field_type_id' => $textarea]);

    expect($field->fresh()->field_type_id)->toBe($textarea);
});

it('[FE-CHANGE-09] satu jawaban pun sudah mengunci tipe field', function () {
    ['field' => $field, 'submission' => $submission] = fieldTerjawab();

    FormFieldResponse::create([
        'form_submission_id' => $submission->id,
        'form_field_id' => $field->id,
        'value' => 'Satu-satunya isi',
    ]);

    expect(fn () => $field->update(['field_type_id' => FieldType::firstOrCreate(['name' => 'number'])->id]))
        ->toThrow(ValidationException::class);
});

it('[FE-CHANGE-10] kebutuhan tipe baru dilayani field baru', function () {
    ['field' => $lama, 'submission' => $submission] = fieldTerjawab();

    FormFieldResponse::create([
        'form_submission_id' => $submission->id,
        'form_field_id' => $lama->id,
        'value' => 'Isi lama',
    ]);

    $baru = FormField::create([
        'form_id' => $lama->form_id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'select'])->id,
        'label' => 'Ringkasan Terstruktur',
        'is_required' => false,
        'order' => 2,
    ]);

    // Field lama tetap apa adanya; BR-FE-11 memastikan field baru ini tidak
    // menghalangi draft yang sudah berjalan.
    expect($baru->exists)->toBeTrue()
        ->and($lama->fresh()->fieldType->name)->toBe('text');
});

// ---------- BR-FE-24: opsi pilihan tidak dihapus permanen ----------

it('[FE-CHANGE-11] opsi yang pernah dipilih tidak dihapus permanen', function () {
    ['field' => $field, 'submission' => $submission] = fieldTerjawab('select');

    $opsi = FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'Hibah Internal']);

    foreach (range(1, 5) as $i) {
        FormFieldResponse::create([
            'form_submission_id' => FormSubmission::factory()->create()->id,
            'form_field_id' => $field->id,
            'value' => 'Hibah Internal',
        ]);
    }

    $opsi->delete();

    expect(FormFieldOption::find($opsi->id))->toBeNull()
        ->and(FormFieldOption::withTrashed()->find($opsi->id))->not->toBeNull()
        // Jawaban lama tetap terbaca — inilah alasan penghapusannya lunak.
        ->and(FormFieldResponse::where('value', 'Hibah Internal')->count())->toBe(5);
});

it('[FE-CHANGE-12] opsi nonaktif berhenti ditawarkan untuk jawaban baru', function () {
    ['field' => $field] = fieldTerjawab('select');

    $aktif = FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'Hibah Eksternal']);
    $mati = FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'Hibah Internal']);
    $mati->delete();

    $ditawarkan = $field->fresh()->formFieldOptions()->pluck('label')->all();

    // Persis BR-BUD-05 pada komponen anggaran: berhenti ditawarkan, tanpa
    // menghapus jejak yang sudah memakainya.
    expect($ditawarkan)->toContain('Hibah Eksternal')
        ->and($ditawarkan)->not->toContain('Hibah Internal');
});

it('[FE-CHANGE-13] opsi yang belum pernah dipilih boleh dihapus', function () {
    ['field' => $field] = fieldTerjawab('select');

    $opsi = FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'Uji Coba']);
    $opsi->delete();

    expect($field->fresh()->formFieldOptions()->count())->toBe(0);
});

it('[FE-CHANGE-14] menyimpan field tanpa menyebut opsi tidak menghapus opsinya', function () {
    $admin = formAdmin();
    ['field' => $field] = fieldTerjawab('select');

    foreach (['A', 'B', 'C', 'D'] as $label) {
        FormFieldOption::create(['form_field_id' => $field->id, 'label' => $label]);
    }

    // Payload tanpa kunci `options` berarti "tidak ada perubahan opsi". Versi
    // lama membaca ketiadaan kunci sebagai perintah menghapus semuanya.
    $this->actingAs($admin)->put(route('admin.forms.update', $field->form_id), [
        'title' => 'Form Uji',
        'form_type_id' => $field->form->form_type_id,
        'fields' => [[
            'id' => $field->id,
            'field_type_id' => $field->field_type_id,
            'label' => 'Ringkasan',
            'is_required' => false,
        ]],
    ]);

    expect($field->fresh()->formFieldOptions()->count())->toBe(4);
});
