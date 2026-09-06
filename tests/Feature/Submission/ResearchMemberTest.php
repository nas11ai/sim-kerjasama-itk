<?php

// tests/Feature/Submission/ResearchMemberTest.php
//
// BR-SM-08 — sisi anggota dari BR-REV-02. Larangannya dua arah: reviewer
// tidak bisa jadi anggota, anggota tidak bisa jadi reviewer. Satu guard,
// dua pemanggil — sisi reviewer dijaga [REV-ASSIGN-07] di M2.
//
// Jumlah anggota dibatasi `scheme.max_members`; skema yang tidak
// menyatakannya tidak membatasi.

use App\Actions\FormSubmission\AddResearchMember;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Draft;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission} */
function memberFixture(int $maxMembers = 4): array
{
    $owner = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $owner->id]);

    $scheme = Scheme::factory()->create(['max_members' => $maxMembers]);

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
    ]);

    return [$owner, $submission];
}

function candidate(): User
{
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id]);

    return $user;
}

it('[SM-MEMBER-01] reviewer pengajuan ini tidak bisa jadi anggotanya', function () {
    [, $submission] = memberFixture();

    $person = candidate();
    $reviewer = Reviewer::factory()->create(['user_id' => $person->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    AddResearchMember::run($submission, $person, ResearchMember::ROLE_MEMBER);
})->throws(ValidationException::class);

it('[SM-MEMBER-02] anggota bisa ditambahkan sebagai co_investigator', function () {
    [, $submission] = memberFixture();

    $member = AddResearchMember::run($submission, candidate(), ResearchMember::ROLE_CO_INVESTIGATOR);

    expect($member->role)->toBe(ResearchMember::ROLE_CO_INVESTIGATOR)
        ->and($member->status)->toBe('active');
});

it('[SM-MEMBER-03] jumlah anggota dibatasi skema', function () {
    [, $submission] = memberFixture(maxMembers: 2);

    AddResearchMember::run($submission, candidate(), ResearchMember::ROLE_MEMBER);
    AddResearchMember::run($submission, candidate(), ResearchMember::ROLE_MEMBER);

    // Yang ketiga melewati batas.
    AddResearchMember::run($submission, candidate(), ResearchMember::ROLE_MEMBER);
})->throws(ValidationException::class);

it('[SM-MEMBER-03] anggota non-aktif tidak ikut dihitung batas', function () {
    [, $submission] = memberFixture(maxMembers: 1);

    $first = AddResearchMember::run($submission, candidate(), ResearchMember::ROLE_MEMBER);
    $first->update(['status' => 'inactive']);

    // Anggota yang sudah keluar tidak memakai kuota.
    $second = AddResearchMember::run($submission, candidate(), ResearchMember::ROLE_MEMBER);

    expect($second->status)->toBe('active');
});

it('[SM-MEMBER-01] ketua pengaju tidak bisa ditambahkan sebagai anggota', function () {
    [$owner, $submission] = memberFixture();

    // Ia sudah penanggung jawabnya — mendaftarkannya lagi sebagai anggota
    // menduplikasi peran yang sama.
    AddResearchMember::run($submission, $owner, ResearchMember::ROLE_MEMBER);
})->throws(ValidationException::class);
