<?php

// tests/Feature/Identity/NidnVerificationTest.php
//
// BR-IAM-03, BR-IAM-04, BR-IAM-13 — tiga gerbang keadaan akun yang sering
// dianggap satu.
//
//   verification_status  → menggerbang SUBMIT
//   User.is_active       → menggerbang LOGIN
//   isComplete()         → menggerbang PEMBUATAN pengajuan
//
// Satu user bisa lolos yang satu sambil tertahan yang lain, dan itulah yang
// membuat ketiganya harus diuji terpisah.

use App\Actions\FormSubmission\StartSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Actions\Identity\RejectNidnVerification;
use App\Actions\Identity\VerifyNidn;
use App\Enums\VerificationStatus;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\NidnVerificationRejectedNotification;
use App\Notifications\NidnVerifiedNotification;
use App\States\Submission\Draft;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function verifierOperator(): User
{
    Permission::findOrCreate('users.verify', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.verify');

    return $operator;
}

/** Pengajuan DRAFT siap submit milik pemilik yang profilnya bisa diatur. */
function verificationFixture(string $state = 'pending'): array
{
    $fx = wizardFixture();

    $profile = $fx['user']->userProfile;
    $profile->update([
        'verification_status' => $state,
        'rejection_reason' => $state === 'rejected' ? 'NIDN tidak ditemukan di PDDikti.' : null,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    return ['fx' => $fx, 'profile' => $profile->fresh(), 'submission' => $submission];
}

it('[IAM-VERIFY-02] user pending tidak bisa submit', function () {
    ['fx' => $fx, 'submission' => $submission] = verificationFixture('pending');

    expect(fn () => SubmitFormSubmission::run($submission, $fx['user']))
        ->toThrow(ValidationException::class)
        ->and($submission->fresh()->is_submitted)->toBeFalse();
});

it('[IAM-VERIFY-03] verifikasi NIDN membuka kemampuan submit', function () {
    Notification::fake();
    ['fx' => $fx, 'profile' => $profile, 'submission' => $submission] = verificationFixture('pending');

    VerifyNidn::run($profile);

    expect($profile->fresh()->verification_status)->toBe(VerificationStatus::Active);
    Notification::assertSentTo($fx['user'], NidnVerifiedNotification::class);

    // Relasi profil pada objek user masih memegang nilai lama; di produksi
    // user dimuat ulang tiap request, jadi yang perlu disegarkan hanya di sini.
    SubmitFormSubmission::run($submission, $fx['user']->fresh());

    expect($submission->fresh()->is_submitted)->toBeTrue();
});

it('[IAM-VERIFY-06] user pending tetap boleh menyiapkan draft', function () {
    $fx = wizardFixture();
    $fx['user']->userProfile->update(['verification_status' => 'pending']);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->last_completed_step)->toBe(1);
});

it('[IAM-VERIFY-11] user pending tetap boleh menyunting draft yang sudah ada', function () {
    ['fx' => $fx, 'submission' => $submission] = verificationFixture('pending');

    // Gerbangnya di submit, bukan di update — implementasi yang memasangnya di
    // update() akan lolos IAM-VERIFY-06 tapi gagal di sini.
    expect($fx['user']->can('update', $submission))->toBeTrue();
});

it('[IAM-VERIFY-04] profil belum lengkap memblokir pembuatan pengajuan', function () {
    $fx = wizardFixture();
    $fx['user']->userProfile->update([
        'verification_status' => 'active',
        'phone' => null,
        'expertise' => null,
        'photo_path' => null,
    ]);

    expect(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(ValidationException::class);
});

it('[IAM-VERIFY-05] profil lengkap membuka pembuatan pengajuan', function () {
    $fx = wizardFixture();
    $fx['user']->userProfile->update(['verification_status' => 'active']);

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class);
});

it('[IAM-VERIFY-12] profil yang terisi sebagian tetap dihitung belum lengkap', function () {
    $fx = wizardFixture();
    $fx['user']->userProfile->update([
        'verification_status' => 'active',
        'phone' => '0813-9876-5432',
        'expertise' => null,
        'photo_path' => null,
    ]);

    // Pemeriksaan yang berhenti di field pertama akan meloloskan ini.
    expect(fn () => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']))
        ->toThrow(ValidationException::class);
});

it('[IAM-VERIFY-07] penolakan dengan alasan tertulis mengirimkan alasannya', function () {
    Notification::fake();
    ['fx' => $fx, 'profile' => $profile] = verificationFixture('pending');

    RejectNidnVerification::run($profile, 'NIDN tidak ditemukan di PDDikti atas nama yang sama.');

    expect($profile->fresh()->verification_status)->toBe(VerificationStatus::Rejected)
        ->and($profile->fresh()->rejection_reason)->toContain('PDDikti');

    Notification::assertSentTo($fx['user'], NidnVerificationRejectedNotification::class);
});

it('[IAM-VERIFY-08] penolakan tanpa alasan ditolak dan status tidak bergerak', function () {
    ['profile' => $profile] = verificationFixture('pending');

    expect(fn () => RejectNidnVerification::run($profile, '   '))
        ->toThrow(ValidationException::class)
        ->and($profile->fresh()->verification_status)->toBe(VerificationStatus::Pending);
});

it('[IAM-VERIFY-08] jalur HTTP menolak alasan kosong pada field reason', function () {
    ['profile' => $profile] = verificationFixture('pending');

    $this->actingAs(verifierOperator())
        ->post(route('admin.verifications.reject', $profile), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($profile->fresh()->verification_status)->toBe(VerificationStatus::Pending);
});

it('[IAM-VERIFY-09] akun yang verifikasinya ditolak tidak bisa submit', function () {
    ['fx' => $fx, 'submission' => $submission] = verificationFixture('rejected');

    expect(fn () => SubmitFormSubmission::run($submission, $fx['user']))
        ->toThrow(ValidationException::class)
        ->and($submission->fresh()->is_submitted)->toBeFalse();
});

it('[IAM-VERIFY-10] akun yang ditolak masih bisa diverifikasi setelah NIDN dikoreksi', function () {
    Notification::fake();
    ['profile' => $profile] = verificationFixture('rejected');

    VerifyNidn::run($profile);

    expect($profile->fresh()->verification_status)->toBe(VerificationStatus::Active)
        // Alasan lama dibersihkan — ia sudah tidak berlaku.
        ->and($profile->fresh()->rejection_reason)->toBeNull();
});

it('[IAM-VERIFY-01] antrian hanya memuat akun berstatus pending', function () {
    ['profile' => $pending] = verificationFixture('pending');
    ['profile' => $ditolak] = verificationFixture('rejected');

    $aktif = UserProfile::factory()->create(['user_id' => User::factory()->create()->id]);

    $antrian = UserProfile::awaitingVerification()->pluck('id')->all();

    expect($antrian)->toContain($pending->id)
        ->and($antrian)->not->toContain($ditolak->id)
        ->and($antrian)->not->toContain($aktif->id);
});

it('[IAM-VERIFY-01] Operator membuka antrian verifikasi', function () {
    ['profile' => $profile] = verificationFixture('pending');

    $this->actingAs(verifierOperator())
        ->get(route('admin.verifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Identity/VerificationQueuePage')
            ->has('pending', 1)
            ->where('pending.0.id', $profile->id)
            ->etc());
});
