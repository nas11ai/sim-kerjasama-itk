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
use App\Models\FormSubmission;

/** @return array{fx: array<string, mixed>, submission: FormSubmission} */
function optimisticLockFixture(): array
{
    $fx = wizardFixture();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    return ['fx' => $fx, 'submission' => $submission];
}

it('[FE-LOCK-01] penyimpanan lolos saat tidak ada yang mendahului', function () {
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), draftPayload(
            $fx['form'],
            $fx['period'],
            $fx['detail']->formPhase,
            $submission->updated_at->toIso8601String(),
        ))
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
        ->post(route('user.form-submission.save-draft'), draftPayload(
            $fx['form'],
            $fx['period'],
            $fx['detail']->formPhase,
            $capWaktuDilihatPenyunting,
        ))
        ->assertStatus(409);
});

it('[FE-LOCK-02] pesan penolakan meminta memuat ulang halaman', function () {
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();

    $lama = $submission->updated_at->toIso8601String();
    $this->travel(5)->minutes();
    $submission->forceFill(['last_completed_step' => 2])->save();

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), draftPayload(
            $fx['form'],
            $fx['period'],
            $fx['detail']->formPhase,
            $lama,
        ))
        ->assertSee('Muat ulang halaman', false);
});

it('[FE-LOCK-03] penyimpanan tanpa last_updated_at ditolak sebagai error validasi', function () {
    ['fx' => $fx] = optimisticLockFixture();

    // 422, bukan 409: field yang tidak dikirim adalah permintaan cacat bentuk,
    // dan memuat ulang halaman tidak akan memperbaikinya.
    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), draftPayload(
            $fx['form'],
            $fx['period'],
            $fx['detail']->formPhase,
        ))
        ->assertSessionHasErrors('last_updated_at');
});

it('[FE-LOCK-04] penolakan tidak mengubah data', function () {
    ['fx' => $fx, 'submission' => $submission] = optimisticLockFixture();

    $lama = $submission->updated_at->toIso8601String();

    $this->travel(5)->minutes();
    $submission->forceFill(['last_completed_step' => 7])->save();

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), draftPayload(
            $fx['form'],
            $fx['period'],
            $fx['detail']->formPhase,
            $lama,
        ))
        ->assertStatus(409);

    // Perubahan orang yang mendahului tetap utuh, dan tulisan penyunting yang
    // kalah tidak menyelinap masuk sebagian.
    expect($submission->fresh()->last_completed_step)->toBe(7)
        ->and(FormSubmission::find($submission->id)->formFieldResponses()->count())->toBe(0);
});
