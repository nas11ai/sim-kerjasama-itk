<?php

// tests/Feature/Submission/OwnershipTest.php
//
// BR-SM-09 dicerminkan BR-IAM-10 — lead researcher yang dinonaktifkan tidak
// boleh meninggalkan pengajuan tanpa penanggung jawab.
//
// Penerimanya wajib co_investigator **aktif**: member biasa tidak pernah
// memegang tanggung jawab pengajuan, jadi memindahkannya ke sana hanya
// memindahkan masalah.

use App\Actions\FormSubmission\DeactivateUser;
use App\Actions\FormSubmission\TransferSubmissionOwnership;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission} */
function ownershipFixture(string $state = UnderReview::class): array
{
    $owner = User::factory()->create();
    // Setiap peneliti punya UserProfile — itu yang menautkannya ke
    // ResearchMember saat kepemilikan dipindahkan.
    UserProfile::factory()->create(['user_id' => $owner->id]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => null,
        'status' => $state,
    ]);

    return [$owner, $submission];
}

function memberOn(FormSubmission $submission, string $role, string $status = 'active'): User
{
    $user = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $user->id]);

    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $profile->id,
        'role' => $role,
        'status' => $status,
    ]);

    return $user;
}

it('[SM-OWNER-01] kepemilikan pindah ke co-investigator aktif', function () {
    [$owner, $submission] = ownershipFixture();
    $successor = memberOn($submission, ResearchMember::ROLE_CO_INVESTIGATOR);

    TransferSubmissionOwnership::run($submission, $successor);

    expect($submission->fresh()->submitted_by)->toBe($successor->id);
});

it('[SM-OWNER-01] pemilik lama tetap terdaftar sebagai anggota', function () {
    [$owner, $submission] = ownershipFixture();
    $successor = memberOn($submission, ResearchMember::ROLE_CO_INVESTIGATOR);

    TransferSubmissionOwnership::run($submission, $successor);

    // Kalau pemilik lama hilang dari daftar, jejak siapa yang mengerjakan
    // penelitian ini ikut hilang.
    $stillMember = $submission->fresh()->researchMembers()
        ->whereHas('userProfile', fn ($q) => $q->where('user_id', $owner->id))
        ->exists();

    expect($stillMember)->toBeTrue();
});

it('[SM-OWNER-02] transfer ke member biasa ditolak', function () {
    [, $submission] = ownershipFixture();
    $plainMember = memberOn($submission, ResearchMember::ROLE_MEMBER);

    TransferSubmissionOwnership::run($submission, $plainMember);
})->throws(ValidationException::class);

it('[SM-OWNER-02] transfer ke co-investigator non-aktif ditolak', function () {
    [, $submission] = ownershipFixture();
    $inactive = memberOn($submission, ResearchMember::ROLE_CO_INVESTIGATOR, 'inactive');

    TransferSubmissionOwnership::run($submission, $inactive);
})->throws(ValidationException::class);

it('[SM-OWNER-03] deaktivasi lead ditolak selama masih ada pengajuan aktif', function () {
    [$owner, $submission] = ownershipFixture();
    memberOn($submission, ResearchMember::ROLE_CO_INVESTIGATOR);

    DeactivateUser::run($owner);
})->throws(ValidationException::class);

it('[SM-OWNER-04] tanpa co-investigator aktif, alasannya disebut', function () {
    [$owner] = ownershipFixture();

    try {
        DeactivateUser::run($owner);
        $this->fail('Deaktivasi seharusnya ditolak.');
    } catch (ValidationException $e) {
        $pesan = implode(' ', $e->errors()['user_id']);

        // Operator harus tahu bahwa jalan keluarnya belum ada — bukan sekadar
        // ditolak tanpa keterangan.
        expect($pesan)->toContain('co_investigator');
    }
});

it('[SM-OWNER-03] deaktivasi lolos setelah kepemilikan dipindahkan', function () {
    [$owner, $submission] = ownershipFixture();
    $successor = memberOn($submission, ResearchMember::ROLE_CO_INVESTIGATOR);

    TransferSubmissionOwnership::run($submission, $successor);
    DeactivateUser::run($owner);

    expect($owner->fresh()->is_active)->toBeFalse();
});

it('[SM-OWNER-03] pengajuan yang sudah mati tidak menghalangi deaktivasi', function () {
    [$owner] = ownershipFixture(Rejected::class);

    DeactivateUser::run($owner);

    expect($owner->fresh()->is_active)->toBeFalse();
});
