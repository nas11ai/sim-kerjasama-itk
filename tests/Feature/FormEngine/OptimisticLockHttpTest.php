<?php

// tests/Feature/FormEngine/OptimisticLockHttpTest.php
//
// BR-FE-14 / BR-SM-12 — dua orang yang menyunting pengajuan yang sama tidak
// boleh saling menimpa diam-diam.
//
// OptimisticLock sudah ada sebagai kelas dan sudah punya unit test, tapi
// sampai irisan ini **tidak satu pun jalur simpan memanggilnya**. Aturan yang
// tidak dipanggil tidak menjaga apa pun; berkas ini menguji jalur HTTP-nya.

use App\Actions\FormSubmission\StartSubmission;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;

/** @return array{fx: array<string, mixed>, submission: FormSubmission} */
function optimisticLockFixture(): array
{
    $fx = wizardFixture();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    return ['fx' => $fx, 'submission' => $submission];
}

/** @return list<array{form_field_id: int, value: string}> satu jawaban teks di langkah 2 */
function lockResponses(array $fx): array
{
    return [['form_field_id' => wizardTextField($fx['form'])->id, 'value' => 'Contoh nilai']];
}

it('[FE-LOCK-01] penyimpanan lolos saat tidak ada yang mendahului', function () {
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.wizard.save', [$submission, 2]), wizardPayload($submission->updated_at->toIso8601String(), 3, lockResponses($fx)))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('[FE-LOCK-02] penyimpanan ditolak 409 saat sudah didahului orang lain', function () {
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();

    $capWaktuDilihatPenyunting = $submission->updated_at->toIso8601String();

    // Orang lain menyimpan lebih dulu — updated_at bergeser.
    $this->travel(5)->minutes();
    $submission->forceFill(['last_completed_step' => 2])->save();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.wizard.save', [$submission, 2]), wizardPayload($capWaktuDilihatPenyunting, 3, lockResponses($fx)))
        ->assertStatus(409);
});

it('[FE-LOCK-02] pesan penolakan meminta memuat ulang halaman', function () {
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();

    $lama = $submission->updated_at->toIso8601String();
    $this->travel(5)->minutes();
    $submission->forceFill(['last_completed_step' => 2])->save();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.wizard.save', [$submission, 2]), wizardPayload($lama, 3, lockResponses($fx)))
        ->assertSee('Muat ulang halaman', false);
});

it('[FE-LOCK-03] penyimpanan tanpa last_updated_at ditolak sebagai error validasi', function () {
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();

    // 422, bukan 409: field yang tidak dikirim adalah permintaan cacat bentuk,
    // dan memuat ulang halaman tidak akan memperbaikinya.
    $this->actingAs($fx['user'])
        ->post(route('user.submissions.wizard.save', [$submission, 2]), wizardPayload(null, 3, lockResponses($fx)))
        ->assertSessionHasErrors('last_updated_at');
});

it('[FE-LOCK-04] penolakan tidak mengubah data', function () {
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();

    $lama = $submission->updated_at->toIso8601String();

    $this->travel(5)->minutes();
    $submission->forceFill(['last_completed_step' => 7])->save();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.wizard.save', [$submission, 2]), wizardPayload($lama, 3, lockResponses($fx)))
        ->assertStatus(409);

    // Perubahan orang yang mendahului tetap utuh, dan tulisan penyunting yang
    // kalah tidak menyelinap masuk sebagian.
    expect($submission->fresh()->last_completed_step)->toBe(7)
        ->and(FormSubmission::find($submission->id)->formFieldResponses()->count())->toBe(0);
});

it('[FE-LOCK-02] simpanan kedua dari cap yang sama ditolak 409', function () {
    // W161 — tanpa menggeser updated_at secara manual: simpanan pertama sendiri yang harus menggesernya.
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();
    $field = wizardTextField($fx['form']);
    $cap = $submission->updated_at->toIso8601String();
    $this->travel(1)->seconds();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.wizard.save', [$submission, 2]), wizardPayload($cap, 3, [['form_field_id' => $field->id, 'value' => 'Versi tab A']]))
        ->assertRedirect();
    $this->actingAs($fx['user'])
        ->post(route('user.submissions.wizard.save', [$submission, 2]), wizardPayload($cap, 3, [['form_field_id' => $field->id, 'value' => 'Versi tab B']]))
        ->assertStatus(409);

    expect(FormFieldResponse::query()->where('form_field_id', $field->id)->value('value'))->toBe('Versi tab A');
});
