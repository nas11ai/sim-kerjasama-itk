<?php

// tests/Feature/Review/ConflictOfInterestTest.php
//
// BR-REV-02 — konflik kepentingan reviewer ↔ anggota, saling memblokir.
// Kanonik di BC Review; sisi anggota dinyatakan BR-SM-08, pembatasan
// per-role dinyatakan BR-IAM-07. Satu implementasi, dua pemanggil.
//
// Kedua sisi memakai referensi identitas berbeda — SubmissionReviewer
// menunjuk Reviewer, ResearchMember menunjuk UserProfile — jadi keduanya
// harus diresolusi ke User yang sama sebelum dibandingkan.

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\ConflictOfInterestGuard;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission} */
function conflictSliceSubmission(): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => null,
    ]);

    return [$owner, $submission];
}

it('[REV-ASSIGN-04] submitted_by tidak bisa mereview pengajuannya sendiri', function () {
    [$owner, $submission] = conflictSliceSubmission();

    expect(ConflictOfInterestGuard::conflicts($owner, $submission))->toBeTrue();

    ConflictOfInterestGuard::assertNone($owner, $submission);
})->throws(ValidationException::class);

it('[REV-ASSIGN-07] anggota penelitian tidak bisa mereview pengajuan yang sama', function () {
    [, $submission] = conflictSliceSubmission();

    $member = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $member->id]);
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $profile->id,
        'role' => ResearchMember::ROLE_MEMBER,
        'status' => 'active',
    ]);

    expect(ConflictOfInterestGuard::conflicts($member, $submission))->toBeTrue();
});

it('[REV-ASSIGN-07] reviewer yang sudah ditugaskan tidak bisa ditambahkan sebagai anggota', function () {
    [, $submission] = conflictSliceSubmission();

    // Arah sebaliknya — inilah yang membuat aturan ini saling memblokir,
    // dan alasan BR-SM-08 menyebut "satu Policy, dua pemanggil".
    $person = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $person->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    expect(ConflictOfInterestGuard::conflicts($person, $submission))->toBeTrue();
});

it('[REV-ASSIGN-07] orang tanpa peran apa pun pada pengajuan itu tidak berkonflik', function () {
    [, $submission] = conflictSliceSubmission();

    $outsider = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $outsider->id]);

    expect(ConflictOfInterestGuard::conflicts($outsider, $submission))->toBeFalse();

    // assertNone lolos tanpa melempar — kalau ia melempar, test ini gagal.
    ConflictOfInterestGuard::assertNone($outsider, $submission);

    expect(true)->toBeTrue();
});

it('[REV-ASSIGN-07] peran pada pengajuan lain tidak menimbulkan konflik', function () {
    [, $submission] = conflictSliceSubmission();
    [, $other] = conflictSliceSubmission();

    // Konflik terikat pada satu pengajuan. Menjadi anggota di pengajuan lain
    // tidak boleh menghalangi seseorang mereview pengajuan ini.
    $person = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $person->id]);
    ResearchMember::factory()->create([
        'form_submission_id' => $other->id,
        'user_profile_id' => $profile->id,
        'role' => ResearchMember::ROLE_MEMBER,
        'status' => 'active',
    ]);

    expect(ConflictOfInterestGuard::conflicts($person, $submission))->toBeFalse();
});
