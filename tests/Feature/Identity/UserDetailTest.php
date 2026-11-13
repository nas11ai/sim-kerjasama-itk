<?php

// OPERATOR-08-03 — detail user: profil, peran & hak akses, penghalang deaktivasi
// (BR-IAM-10, SM-OWNER-03/04), dan riwayat yang tetap terbaca (IAM-DEACT-03, BR-REV-10).

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\UnderReview;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function detailViewer(string ...$permissions): User
{
    $user = User::factory()->create(['is_active' => true]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

/** @return array{0: User, 1: FormSubmission} */
function leadWithActiveSubmission(): array
{
    $lead = User::factory()->create(['name' => 'Dr. Rina Wijaya']);
    UserProfile::factory()->create(['user_id' => $lead->id]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $lead->id,
        'status' => UnderReview::class,
        'is_submitted' => true,
    ]);

    return [$lead, $submission];
}

function coInvestigatorOn(FormSubmission $submission): User
{
    $user = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $user->id]);
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $profile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    return $user;
}

it('kontrak detail user', function () {
    [$lead] = leadWithActiveSubmission();

    $this->actingAs(detailViewer('users.manage', 'submissions.view-all'))
        ->get(route('admin.users.show', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Identity/UserShowPage')
            ->hasAll(['user', 'access', 'summary', 'deactivationBlockers', 'submissions', 'reviews', 'tab', 'can'])
            ->where('user.name', 'Dr. Rina Wijaya')
            ->where('tab', 'profile')
            ->where('can.deactivate', true)
            ->where('can.changeRoles', true)
            ->where('can.managePermissions', true)
            ->where('can.appointReviewer', false)
            ->where('access.assignableRoles.0', ['value' => 'researcher', 'label' => 'Peneliti'])
            ->where('submissions.0.role', 'Ketua'));
});

it('hak akses efektif gabungan, grantable tanpa yang sudah langsung', function () {
    $target = User::factory()->create();
    Permission::findOrCreate('reporting.export', 'web');
    $target->givePermissionTo('reporting.export');

    $this->actingAs(detailViewer('users.manage'))->get(route('admin.users.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('access.directPermissions', [['value' => 'reporting.export', 'label' => 'Mengekspor laporan', 'description' => 'Mengunduh data pengajuan dan laporan sebagai berkas.']])
            ->where('access.effectivePermissions', [['value' => 'reporting.export', 'label' => 'Mengekspor laporan', 'description' => 'Mengunduh data pengajuan dan laporan sebagai berkas.']])
            ->where('access.grantablePermissions', fn ($options) => !collect($options)->contains('value', 'reporting.export')
                && collect($options)->contains('value', 'users.verify')));
});

it('Operator users.verify membuka detail, can.deactivate false', function () {
    $this->actingAs(detailViewer('users.verify'))->get(route('admin.users.show', User::factory()->create()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.deactivate', false)
            ->where('can.changeRoles', false)
            ->where('can.managePermissions', false));
});

it('peneliti 403', function () {
    $this->actingAs(detailViewer('submissions.create'))->get(route('admin.users.show', User::factory()->create()))
        ->assertForbidden();
});

it('[SM-OWNER-03] penghalang memuat pengajuan aktif dan manageUrl', function () {
    [$lead, $submission] = leadWithActiveSubmission();
    coInvestigatorOn($submission);

    $this->actingAs(detailViewer('users.manage', 'submissions.view-all'))->get(route('admin.users.show', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->has('deactivationBlockers', 1)
            ->where('deactivationBlockers.0.submissionId', $submission->id)
            ->where('deactivationBlockers.0.hasActiveCoInvestigator', true)
            ->where('deactivationBlockers.0.manageUrl', route('admin.submissions.show', $submission))
            ->where('summary.activeLeadSubmissions', 1)
            ->where('submissions.0.url', route('admin.submissions.show', $submission)));
});

it('[SM-OWNER-03] tanpa view-all penghalang tak menaut ke detail operator', function () {
    [$lead] = leadWithActiveSubmission();

    $this->actingAs(detailViewer('users.manage'))->get(route('admin.users.show', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->where('deactivationBlockers.0.manageUrl', null)
            ->where('submissions.0.url', null));
});

it('[SM-OWNER-04] hasActiveCoInvestigator false bila tak ada co-investigator aktif', function () {
    [$lead] = leadWithActiveSubmission();

    $this->actingAs(detailViewer('users.manage'))->get(route('admin.users.show', $lead))
        ->assertInertia(fn (Assert $page) => $page->where('deactivationBlockers.0.hasActiveCoInvestigator', false));
});

it('[IAM-DEACT-03] detail user nonaktif tetap terbuka dengan riwayatnya', function () {
    [$lead, $submission] = leadWithActiveSubmission();
    $member = coInvestigatorOn($submission);
    $member->update(['is_active' => false]);

    $this->actingAs(detailViewer('users.manage'))->get(route('admin.users.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.status.active', false)
            ->has('submissions', 1)
            ->where('submissions.0.role', 'Co-Investigator'));
});

it('riwayat reviewer memuat penugasan replaced', function () {
    $user = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $user->id]);
    [, $first] = leadWithActiveSubmission();
    [, $second] = leadWithActiveSubmission();
    SubmissionReviewer::query()->create(['form_submission_id' => $first->id, 'reviewer_id' => $reviewer->id, 'status' => 'replaced', 'evaluation_status' => 'pending']);
    SubmissionReviewer::query()->create(['form_submission_id' => $second->id, 'reviewer_id' => $reviewer->id, 'status' => 'active', 'evaluation_status' => 'pending']);

    $this->actingAs(detailViewer('users.verify'))->get(route('admin.users.show', $user))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews', 2)
            ->where('reviews', fn ($rows) => collect($rows)->pluck('assignmentStatus.value')->sort()->values()->all() === ['active', 'replaced'])
            ->where('summary.runningReviews', 1));
});

it('tab tak dikenal menjadi profile', function () {
    $target = User::factory()->create();
    $viewer = detailViewer('users.verify');

    $this->actingAs($viewer)->get(route('admin.users.show', [$target, 'tab' => 'reviews']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'reviews'));
    $this->actingAs($viewer)->get(route('admin.users.show', [$target, 'tab' => 'rahasia']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'profile'));
});

it('BR-IAM-05 — akun nonaktif tidak ditawari "Tunjuk sebagai Reviewer"', function () {
    $target = User::factory()->create(['is_active' => false]);

    $this->actingAs(detailViewer('users.manage', 'reviewers.assign'))
        ->get(route('admin.users.show', $target))
        ->assertInertia(fn (Assert $page) => $page->where('can.appointReviewer', false));
});
