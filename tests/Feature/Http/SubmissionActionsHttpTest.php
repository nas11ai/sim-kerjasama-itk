<?php

// tests/Feature/Http/SubmissionActionsHttpTest.php
//
// Endpoint untuk Action M1/M2 yang sebelumnya hanya ada sebagai unit.
//
// Aturan yang benar tapi tidak tersambung tidak melindungi siapa pun —
// pelajaran dari temuan sesi ini, di mana empat unit M2 lulus test sementara
// aplikasi menjalankan kode legacy sepenuhnya. Berkas ini menjaga tiap Action
// benar-benar terjangkau, dan otorisasinya berlaku di jalur nyata.

use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

function httpOperator(): User
{
    Permission::findOrCreate('submissions.view-all', 'web');
    Permission::findOrCreate('users.manage', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo(['submissions.view-all', 'users.manage']);

    return $operator;
}

function httpOwner(): User
{
    $owner = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $owner->id]);

    return $owner;
}

/* ---------------- target luaran ---------------- */

it('menambah target luaran lewat endpoint', function () {
    OutputTypeDefinition::firstOrCreate(['code' => 'publikasi'], ['name' => 'Publikasi', 'is_active' => true]);

    $owner = httpOwner();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => Draft::class,
    ]);

    $this->actingAs($owner)
        ->post(route('user.submissions.target-outputs.store', $submission), ['output_type' => 'publikasi'])
        ->assertRedirect();

    expect($submission->targetOutputs()->count())->toBe(1);
});

it('menolak target luaran di luar taksonomi lewat endpoint', function () {
    $owner = httpOwner();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => Draft::class,
    ]);

    $this->actingAs($owner)
        ->post(route('user.submissions.target-outputs.store', $submission), ['output_type' => 'podcast'])
        ->assertSessionHasErrors();

    expect($submission->targetOutputs()->count())->toBe(0);
});

it('[SM-TARGET-08] co-investigator ditolak menyunting target lewat endpoint', function () {
    OutputTypeDefinition::firstOrCreate(['code' => 'haki'], ['name' => 'HAKI', 'is_active' => true]);

    $owner = httpOwner();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => Draft::class,
    ]);

    $co = httpOwner();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $co->userProfile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    $this->actingAs($co)
        ->post(route('user.submissions.target-outputs.store', $submission), ['output_type' => 'haki'])
        ->assertForbidden();
});

/* ---------------- anggota penelitian ---------------- */

it('menambah anggota penelitian lewat endpoint', function () {
    $owner = httpOwner();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => Scheme::factory()->create(['max_members' => 4])->id,
        'status' => Draft::class,
    ]);

    $candidate = httpOwner();

    $this->actingAs($owner)
        ->post(route('user.submissions.members.store', $submission), [
            'user_id' => $candidate->id,
            'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        ])
        ->assertRedirect();

    expect($submission->researchMembers()->count())->toBe(1);
});

it('[SM-MEMBER-01] menolak anggota yang sudah jadi reviewer lewat endpoint', function () {
    $owner = httpOwner();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => Draft::class,
    ]);

    $person = httpOwner();
    $reviewer = Reviewer::factory()->create(['user_id' => $person->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->post(route('user.submissions.members.store', $submission), [
            'user_id' => $person->id, 'role' => ResearchMember::ROLE_MEMBER,
        ])
        ->assertSessionHasErrors();

    expect($submission->researchMembers()->count())->toBe(0);
});

/* ---------------- penolakan manual ---------------- */

it('[REV-OUTCOME-07] Operator menolak lewat endpoint', function () {
    $submission = readyForDecision();

    $this->actingAs(httpOperator())
        ->post(route('admin.submissions.reject', $submission), [
            'reason' => 'Metodologi tidak memadai untuk skala yang diajukan.',
        ])
        ->assertRedirect();

    expect($submission->fresh()->status)->toBeInstanceOf(Rejected::class);
});

it('[REV-OUTCOME-07] penolakan tanpa alasan ditolak lewat endpoint', function () {
    $submission = readyForDecision();

    $this->actingAs(httpOperator())
        ->post(route('admin.submissions.reject', $submission), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($submission->fresh()->status)->toBeInstanceOf(UnderReview::class);
});

/* ---------------- transfer kepemilikan ---------------- */

it('[SM-OWNER-01] Operator memindahkan kepemilikan lewat endpoint', function () {
    $owner = httpOwner();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => UnderReview::class,
    ]);

    $successor = httpOwner();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $successor->userProfile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    $this->actingAs(httpOperator())
        ->post(route('admin.submissions.transfer-ownership', $submission), ['successor_id' => $successor->id, 'reason' => 'Ketua pindah institusi'])
        ->assertRedirect();

    expect($submission->fresh()->submitted_by)->toBe($successor->id);
});

/* ---------------- ulir diskusi ---------------- */

it('[REV-DISCUSS-03] reviewer menutup ulir lewat endpoint', function () {
    $submission = FormSubmission::factory()->create([
        'submitted_by' => httpOwner()->id, 'scheme_id' => null, 'status' => UnderReview::class,
    ]);

    $reviewerUser = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $reviewerUser->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    $summary = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi.',
    ]);

    $this->actingAs($reviewerUser)
        ->post(route('review-summaries.resolve', $summary))
        ->assertRedirect();

    expect($summary->fresh()->status)->toBe('resolved');
});

it('[REV-DISCUSS-04] researcher ditolak menutup ulir lewat endpoint', function () {
    $owner = httpOwner();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => UnderReview::class,
    ]);

    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    $summary = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Catatan.',
    ]);

    $this->actingAs($owner)
        ->post(route('review-summaries.resolve', $summary))
        ->assertForbidden();

    expect($summary->fresh()->status)->toBe('open');
});

/* ---------------- penggantian reviewer ---------------- */

it('[REV-REASSIGN-01] Operator mengganti reviewer lewat endpoint', function () {
    $submission = FormSubmission::factory()->create([
        'submitted_by' => httpOwner()->id, 'scheme_id' => null, 'status' => UnderReview::class,
    ]);

    $outgoing = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(), 'end_date' => now()->addMonth(),
    ]);
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $outgoing->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $incoming = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(), 'end_date' => now()->addMonth(),
    ]);

    $operator = httpOperator();
    $operator->givePermissionTo(Permission::findOrCreate('reviewers.assign', 'web'));

    $this->actingAs($operator)
        ->post(route('admin.submissions.replace-reviewer', [$submission, $outgoing]), [
            'incoming_reviewer_id' => $incoming->id,
        ])
        ->assertRedirect();

    expect($submission->submissionReviewers()->where('reviewer_id', $incoming->id)->where('status', 'active')->exists())
        ->toBeTrue();
});

/* ---------------- deaktivasi user ---------------- */

it('[SM-OWNER-03] deaktivasi ditolak selama masih ada pengajuan aktif', function () {
    $owner = httpOwner();
    FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => UnderReview::class,
    ]);

    $this->actingAs(httpOperator())
        ->post(route('admin.users.deactivate', $owner))
        ->assertSessionHasErrors();

    expect($owner->fresh()->is_active)->toBeTrue();
});
