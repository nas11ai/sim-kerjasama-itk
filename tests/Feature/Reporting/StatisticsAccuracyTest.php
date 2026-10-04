<?php

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Role::findOrCreate('Admin')->givePermissionTo(Permission::findOrCreate('users.manage'));
});

function reportingAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    return $user;
}

/**
 * Satu fase dengan satu form; tiap state di $states mendapat satu pengajuan.
 *
 * @param  list<class-string>  $states
 */
function phaseWithSubmissions(array $states): FormPhaseDetail
{
    $detail = FormPhaseDetail::factory()->create();
    $formId = $detail->formAccessControl->form_id;

    foreach ($states as $state) {
        FormSubmission::factory()->create(['form_id' => $formId, 'status' => $state]);
    }

    return $detail;
}

/** @return list<class-string> */
function everyState(): array
{
    return [
        Draft::class, Submitted::class, Resubmitted::class, UnderReview::class,
        NeedsRevision::class, Approved::class, Rejected::class, Withdrawn::class,
    ];
}

test('BR-RPT-06 rincian status fase menghitung state yang sebenarnya dan melewatkan draft', function () {
    phaseWithSubmissions(everyState());

    $this->actingAs(reportingAdmin())
        ->get(route('admin.stats.form-phase'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formPhaseStatus.0.total_submissions', 7)
            ->where('formPhaseStatus.0.pending', 2)
            ->where('formPhaseStatus.0.under_review', 1)
            ->where('formPhaseStatus.0.revision', 1)
            ->where('formPhaseStatus.0.approved', 1)
            ->where('formPhaseStatus.0.rejected', 1)
            ->where('formPhaseStatus.0.withdrawn', 1));
});

test('BR-RPT-06 total per status dikelompokkan ke kelompok dasbor tanpa draft', function () {
    phaseWithSubmissions(everyState());

    $this->actingAs(reportingAdmin())
        ->get(route('admin.stats.form-submission'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totalByStatus', fn ($rows) => collect($rows)
                ->mapWithKeys(fn ($row) => [$row['status'] => (int) $row['total']])
                ->sortKeys()
                ->all() === [
                    'approved' => 1,
                    'pending' => 2,
                    'rejected' => 1,
                    'revision' => 1,
                    'under_review' => 1,
                    'withdrawn' => 1,
                ]));
});

test('total user fakultas menghitung user dari setiap fakultas, bukan satu fakultas lama', function () {
    $profileIn = function (Organization $unit): void {
        UserProfile::factory()->create(['organization_id' => $unit->id]);
    };

    foreach (['Fakultas A', 'Fakultas B'] as $name) {
        $faculty = Organization::create(['name' => $name, 'type' => 'faculty']);
        $prodi = Organization::create([
            'name' => "Prodi {$name}", 'type' => 'study_program', 'parent_id' => $faculty->id,
        ]);
        $profileIn($prodi);
    }
    $profileIn(Organization::where('name', 'Fakultas A')->firstOrFail());

    $this->actingAs(reportingAdmin())
        ->get(route('admin.stats.user'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totalFaculty', 3)
            ->where('totalProdi', 2));
});
