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
use App\Actions\FormSubmission\RemoveResearchMember;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Illuminate\Auth\Access\AuthorizationException;
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

// ---------- BR-SM-18: mengeluarkan anggota ----------

it('[SM-MEMBER-04] ketua pengaju mengeluarkan anggota', function () {
    [$owner, $submission] = memberFixture();
    $sari = candidate();

    $member = AddResearchMember::run($submission, $sari, ResearchMember::ROLE_MEMBER);

    RemoveResearchMember::run($submission, $member, $owner);

    expect(ResearchMember::query()->whereKey($member->id)->exists())->toBeFalse();
});

it('[SM-MEMBER-05] mengeluarkan anggota membuka kembali kuota skema', function () {
    // Batas 1 orang: kuota penuh setelah satu anggota masuk.
    [$owner, $submission] = memberFixture(1);

    $member = AddResearchMember::run($submission, candidate(), ResearchMember::ROLE_MEMBER);

    expect(fn () => AddResearchMember::run($submission->fresh(), candidate(), ResearchMember::ROLE_MEMBER))
        ->toThrow(ValidationException::class);

    RemoveResearchMember::run($submission, $member, $owner);

    // Inilah yang dijanjikan layar: "keluarkan anggota lain sebelum menambah".
    $tono = candidate();
    AddResearchMember::run($submission->fresh(), $tono, ResearchMember::ROLE_MEMBER);

    expect($submission->fresh()->researchMembers()->where('status', 'active')->count())->toBe(1);
});

it('[SM-MEMBER-06] ketua pengaju tidak bisa mengeluarkan dirinya sendiri', function () {
    [$owner, $submission] = memberFixture();

    // Baris seperti ini tidak lahir lewat AddResearchMember — guard konflik
    // kepentingan menolaknya. Ia bisa ada dari data lama, dan justru karena
    // itu penjagaannya tidak boleh mengandalkan jalur masuknya saja.
    $member = ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $owner->userProfile->id,
        'role' => ResearchMember::ROLE_MEMBER,
        'status' => 'active',
    ]);

    expect(fn () => RemoveResearchMember::run($submission, $member, $owner))
        ->toThrow(ValidationException::class)
        ->and(ResearchMember::query()->whereKey($member->id)->exists())->toBeTrue();
});

it('[SM-MEMBER-07] anggota biasa tidak bisa mengeluarkan anggota lain', function () {
    [, $submission] = memberFixture();

    $sari = candidate();
    AddResearchMember::run($submission, $sari, ResearchMember::ROLE_MEMBER);
    $tono = candidate();
    $member = AddResearchMember::run($submission->fresh(), $tono, ResearchMember::ROLE_MEMBER);

    expect(fn () => RemoveResearchMember::run($submission, $member, $sari))
        ->toThrow(AuthorizationException::class)
        ->and(ResearchMember::query()->whereKey($member->id)->exists())->toBeTrue();
});

it('[SM-MEMBER-08] anggota tidak bisa dikeluarkan pada status terminal', function (string $state) {
    [$owner, $submission] = memberFixture();
    $member = AddResearchMember::run($submission, candidate(), ResearchMember::ROLE_MEMBER);

    $submission->update(['status' => $state]);

    expect(fn () => RemoveResearchMember::run($submission->fresh(), $member, $owner))
        ->toThrow(AuthorizationException::class)
        ->and(ResearchMember::query()->whereKey($member->id)->exists())->toBeTrue();
})->with([Approved::class, Rejected::class, Withdrawn::class]);
