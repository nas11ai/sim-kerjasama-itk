<?php

// tests/Feature/Submission/SubmissionAccessHttpTest.php
//
// BR-SM-07 — lapis akses kedua di luar FormAccessControl. Test unit di
// tests/Unit/FormSubmissionIsAccessibleByTest.php sudah membuktikan aturannya;
// berkas ini membuktikan controller benar-benar memakainya.

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\FormAccessService;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('submissions.view-all', 'web');
});

/** @return array{0: User, 1: FormSubmission} */
function accessSliceSubmission(): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => UnderReview::class,
    ]);

    return [$owner, $submission];
}

/** ResearchMember menunjuk UserProfile, bukan User. */
function accessSliceEnrol(FormSubmission $submission, User $user, string $role): void
{
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
        'role' => $role,
        'status' => 'active',
    ]);
}

function accessSliceAssignReviewer(FormSubmission $submission, User $user): void
{
    $reviewer = Reviewer::factory()->create(['user_id' => $user->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
}

it('[SM-ACCESS-01] pemilik melihat pengajuannya', function () {
    [$owner, $submission] = accessSliceSubmission();

    $this->actingAs($owner)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();
});

it('[SM-ACCESS-02] anggota penelitian melihat pengajuan yang ia ikuti', function () {
    [, $submission] = accessSliceSubmission();
    $member = User::factory()->create();
    accessSliceEnrol($submission, $member, ResearchMember::ROLE_CO_INVESTIGATOR);

    $this->actingAs($member)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();
});

it('[SM-ACCESS-03] researcher lain di organisasi yang sama ditolak', function () {
    // Prekondisi yang membedakan skenario ini dari "orang asing ditolak":
    // Andi seorganisasi dengan Budi DAN Form-nya terbuka untuk organisasi itu.
    // Kalau FormAccessControl dipakai sebagai penjaga detail submission, Andi
    // akan lolos — persis regresi yang BR-SM-07 ada untuk mencegah.
    [$budi, $organization] = actingResearcher();

    $andi = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $andi->id,
        'organization_id' => $organization->id,
    ]);
    $andi->givePermissionTo('submissions.create');

    $form = Form::factory()->create();
    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $budi->id,
        'status' => UnderReview::class,
    ]);

    // Form-nya memang terbuka untuk Andi...
    expect(app(FormAccessService::class)->canAccessForm($andi, $form))->toBeTrue();

    // ...tapi isi submission-nya tidak. Andi bukan anggota "P-001".
    $this->actingAs($andi)
        ->get(route('user.submissions.show', $submission))
        ->assertStatus(403);
});

it('[SM-ACCESS-04] operator melihat semua lewat submissions.view-all', function () {
    [, $submission] = accessSliceSubmission();
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    $this->actingAs($operator)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();
});

it('[SM-ACCESS-05] reviewer yang ditugaskan melihat submission itu', function () {
    [, $submission] = accessSliceSubmission();
    $reviewerUser = User::factory()->create();
    accessSliceAssignReviewer($submission, $reviewerUser);

    $this->actingAs($reviewerUser)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();
});

it('[SM-ACCESS-06] reviewer yang tidak ditugaskan ditolak', function () {
    [, $submission] = accessSliceSubmission();
    $reviewerUser = User::factory()->create();
    Reviewer::factory()->create(['user_id' => $reviewerUser->id]);

    $this->actingAs($reviewerUser)
        ->get(route('user.submissions.show', $submission))
        ->assertStatus(403);
});
