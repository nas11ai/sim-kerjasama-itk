<?php

// tests/Feature/Submission/WithdrawTest.php
//
// BR-SM-11 — penarikan hanya lewat Operator, hanya dari APPROVED, dan tidak
// dapat dibatalkan. State machine sengaja dijaga asiklik: membalikkan
// penarikan menuntut seluruh pembekuan hilir ikut dipulihkan dengan benar,
// untuk peristiwa yang jarang.

use App\Actions\FormSubmission\WithdrawSubmission;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Spatie\Permission\Models\Permission;

function withdrawOperator(): User
{
    Permission::findOrCreate('submissions.view-all', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    return $operator;
}

/** @return array{0: User, 1: FormSubmission} */
function withdrawFixture(string $state = Approved::class): array
{
    $owner = User::factory()->create();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => null,
        'status' => $state,
    ]);

    return [$owner, $submission];
}

it('[SM-WITHDRAW-01] Operator menarik pengajuan yang sudah disetujui', function () {
    [, $submission] = withdrawFixture();

    WithdrawSubmission::run($submission, withdrawOperator());

    expect($submission->fresh()->status)->toBeInstanceOf(Withdrawn::class);
});

it('[SM-WITHDRAW-02] researcher tidak bisa menarik pengajuannya sendiri', function () {
    [$owner, $submission] = withdrawFixture();

    // Penarikan bukan self-service — pemilik justru pihak yang tidak boleh.
    expect($owner->can('withdraw', $submission))->toBeFalse();

    WithdrawSubmission::run($submission, $owner);
})->throws(AuthorizationException::class);

it('[SM-WITHDRAW-03] menarik pengajuan yang belum disetujui ditolak', function () {
    [, $submission] = withdrawFixture(UnderReview::class);

    WithdrawSubmission::run($submission, withdrawOperator());
})->throws(AuthorizationException::class);

it('[SM-WITHDRAW-04] data historis tetap utuh setelah penarikan', function () {
    [$owner, $submission] = withdrawFixture();

    $profile = UserProfile::factory()->create(['user_id' => $owner->id]);
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $profile->id,
        'role' => ResearchMember::ROLE_MEMBER,
        'status' => 'active',
    ]);
    BudgetLineItem::factory()->create(['form_submission_id' => $submission->id]);

    WithdrawSubmission::run($submission, withdrawOperator());

    // Membekukan bukan menghapus: angka anggaran dan daftar anggota tetap
    // jadi dasar audit setelah pengajuan ditarik.
    expect($submission->fresh()->status)->toBeInstanceOf(Withdrawn::class)
        ->and($submission->researchMembers()->count())->toBe(1)
        ->and($submission->budgetLineItems()->count())->toBe(1);
});

it('[SM-WITHDRAW-05] penarikan tidak dapat dibatalkan', function () {
    [, $submission] = withdrawFixture();

    WithdrawSubmission::run($submission, withdrawOperator());

    // WITHDRAWN terminal. Kalau perpindahan ini sah, seluruh pembekuan
    // hilir — siklus monev, luaran — harus ikut dipulihkan, dan tidak ada
    // yang menjamin itu benar.
    $submission->fresh()->status->transitionTo(Approved::class);
})->throws(TransitionNotFound::class);

it('[SM-WITHDRAW-01] Operator menarik lewat jalur HTTP', function () {
    [, $submission] = withdrawFixture();

    $this->actingAs(withdrawOperator())
        ->post(route('admin.submissions.withdraw', $submission))
        ->assertRedirect();

    expect($submission->fresh()->status)->toBeInstanceOf(Withdrawn::class);
});

it('[SM-WITHDRAW-02] pemilik ditolak lewat jalur HTTP', function () {
    [$owner, $submission] = withdrawFixture();

    $this->actingAs($owner)
        ->post(route('admin.submissions.withdraw', $submission))
        ->assertForbidden();

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});
