<?php

// tests/Feature/Submission/OperatorActionReasonTest.php
//
// W26 — alasan pengalihan dan penarikan wajib, catatan penggantian reviewer opsional;
// ketiganya ikut tersimpan di entri audit Action-nya. Plus otorisasi penugasan dan
// penggantian reviewer yang kini dibaca dari Policy (ADR 0015), bukan grup users.manage.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/** @param list<string> $permissions */
function reasonActor(array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function reasonOwner(): User
{
    $owner = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $owner->id]);

    return $owner;
}

function reasonSubmission(string $status): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => reasonOwner()->id, 'scheme_id' => null, 'status' => $status,
    ]);
}

/** @return array{0: FormSubmission, 1: User} */
function reasonTransferFixture(): array
{
    $submission = reasonSubmission(UnderReview::class);
    $successor = reasonOwner();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $successor->userProfile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    return [$submission, $successor];
}

function reasonReviewer(): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

/** @return array{0: FormSubmission, 1: Reviewer, 2: Reviewer} */
function reasonReplaceFixture(): array
{
    $submission = reasonSubmission(UnderReview::class);
    $outgoing = reasonReviewer();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $outgoing->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    return [$submission, $outgoing, reasonReviewer()];
}

/** @return array<string, mixed> */
function latestAuditNew(string $description): array
{
    $entry = Activity::query()->where('description', $description)->latest('id')->firstOrFail();

    return $entry->properties->get('new', []);
}

/* ---------------- pengalihan kepemilikan ---------------- */

it('[SM-OWNER-05] pemindahan kepemilikan tanpa alasan ditolak', function () {
    [$submission, $successor] = reasonTransferFixture();
    $owner = $submission->submitted_by;

    $this->actingAs(reasonActor(['submissions.view-all']))
        ->post(route('admin.submissions.transfer-ownership', $submission), [
            'successor_id' => $successor->id, 'reason' => '',
        ])
        ->assertSessionHasErrors('reason');

    expect($submission->fresh()->submitted_by)->toBe($owner);
});

it('[SM-OWNER-06] alasan pemindahan kepemilikan tercatat di jejak audit', function () {
    [$submission, $successor] = reasonTransferFixture();

    $this->actingAs(reasonActor(['submissions.view-all']))
        ->post(route('admin.submissions.transfer-ownership', $submission), [
            'successor_id' => $successor->id, 'reason' => 'Ketua pindah institusi',
        ])
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->submitted_by)->toBe($successor->id)
        ->and(latestAuditNew('Kepemilikan pengajuan dialihkan'))->toBe(['reason' => 'Ketua pindah institusi']);
});

/* ---------------- penarikan ---------------- */

it('[SM-WITHDRAW-06] penarikan tanpa alasan ditolak', function () {
    $submission = reasonSubmission(Approved::class);

    $this->actingAs(reasonActor(['submissions.view-all']))
        ->post(route('admin.submissions.withdraw', $submission), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($submission->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('[SM-WITHDRAW-07] alasan penarikan tercatat di jejak audit', function () {
    $submission = reasonSubmission(Approved::class);

    $this->actingAs(reasonActor(['submissions.view-all']))
        ->post(route('admin.submissions.withdraw', $submission), ['reason' => 'Duplikasi pendanaan'])
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->status)->toBeInstanceOf(Withdrawn::class)
        ->and(latestAuditNew('Pengajuan ditarik'))->toBe(['reason' => 'Duplikasi pendanaan']);
});

/* ---------------- penggantian reviewer ---------------- */

it('[REV-REASSIGN-04] penggantian tanpa catatan tetap berhasil', function () {
    [$submission, $outgoing, $incoming] = reasonReplaceFixture();

    $this->actingAs(reasonActor(['submissions.view-all', 'reviewers.assign']))
        ->post(route('admin.submissions.replace-reviewer', [$submission, $outgoing]), [
            'incoming_reviewer_id' => $incoming->id, 'note' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($submission->submissionReviewers()->where('reviewer_id', $outgoing->id)->value('status'))->toBe('replaced')
        ->and(latestAuditNew('Reviewer diganti'))->toBe([]);
});

it('[REV-REASSIGN-05] catatan penggantian tercatat di jejak audit', function () {
    [$submission, $outgoing, $incoming] = reasonReplaceFixture();

    $this->actingAs(reasonActor(['submissions.view-all', 'reviewers.assign']))
        ->post(route('admin.submissions.replace-reviewer', [$submission, $outgoing]), [
            'incoming_reviewer_id' => $incoming->id, 'note' => 'Tidak responsif lebih dari 7 hari',
        ])
        ->assertSessionHasNoErrors();

    expect(latestAuditNew('Reviewer diganti'))->toBe(['note' => 'Tidak responsif lebih dari 7 hari']);
});

it('penggantian tanpa reviewers.assign ditolak', function () {
    [$submission, $outgoing, $incoming] = reasonReplaceFixture();

    $this->actingAs(reasonActor(['submissions.view-all']))
        ->post(route('admin.submissions.replace-reviewer', [$submission, $outgoing]), [
            'incoming_reviewer_id' => $incoming->id,
        ])
        ->assertForbidden();
});

/* ---------------- penugasan reviewer ---------------- */

function reasonAssignSubmission(): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => reasonOwner()->id,
        'scheme_id' => Scheme::factory()->create(['rules' => ['min_reviewer_count' => 2]])->id,
        'form_phase_detail_id' => FormPhaseDetail::factory()->create(['needs_review' => true])->id,
        'status' => Submitted::class,
    ]);
}

it('penugasan tanpa reviewers.assign ditolak', function () {
    $submission = reasonAssignSubmission();

    $this->actingAs(reasonActor(['submissions.view-all']))
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [reasonReviewer()->id],
        ])
        ->assertForbidden();

    expect($submission->submissionReviewers()->count())->toBe(0);
});

it('pemegang reviewers.assign tanpa users.manage bisa menugaskan', function () {
    Notification::fake();
    Mail::fake();
    $submission = reasonAssignSubmission();

    $this->actingAs(reasonActor(['submissions.view-all', 'reviewers.assign']))
        ->post(route('admin.submissions.assign-reviewers', $submission), [
            'reviewer_ids' => [reasonReviewer()->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($submission->submissionReviewers()->where('status', 'active')->count())->toBe(1);
});
