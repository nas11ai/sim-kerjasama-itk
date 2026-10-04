<?php

use App\Models\Form;
use App\Models\FormAccessControl;
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

test('total user fakultas dan prodi menghitung unit nonaktif menurut posisinya di pohon', function () {
    $faculty = Organization::create(['name' => 'Fakultas A', 'type' => 'faculty']);
    $prodi = Organization::create([
        'name' => 'Prodi Lama', 'type' => 'study_program', 'parent_id' => $faculty->id,
        'is_active' => false,
    ]);
    UserProfile::factory()->create(['organization_id' => $prodi->id]);

    $this->actingAs(reportingAdmin())
        ->get(route('admin.stats.user'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totalFaculty', 1)
            ->where('totalProdi', 1));
});

test('BR-RPT-06 setiap total pengajuan mengecualikan draft dan sama dengan jumlah kelompok status', function () {
    $detail = phaseWithSubmissions([Draft::class, Submitted::class]);
    $form = $detail->formAccessControl;
    $faculty = Organization::create(['name' => 'Fakultas A', 'type' => 'faculty']);
    $prodi = Organization::create([
        'name' => 'Prodi A', 'type' => 'study_program', 'parent_id' => $faculty->id,
    ]);
    $form->update(['organization_id' => $prodi->id]);
    // DDD 05_reporting — rincian per unit mengikuti unit pengaju, bukan kontrol akses form.
    FormSubmission::query()->where('form_id', $form->form_id)->each(
        fn (FormSubmission $submission) => UserProfile::factory()->create([
            'user_id' => $submission->submitted_by, 'organization_id' => $prodi->id,
        ]),
    );
    $admin = reportingAdmin();

    $this->actingAs($admin)
        ->get(route('admin.stats.form-phase'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formPhaseTotal.0.total_submissions', 1)
            ->where('formPhaseByPeriod.0.total_submissions', 1)
            ->where('formPhaseStatus.0.total_submissions', 1));

    $this->actingAs($admin)
        ->get(route('admin.stats.form-submission'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totalByFaculty.0.total', 1)
            ->where('totalByProdi.0.total', 1)
            ->where('totalByStatus', fn ($rows) => collect($rows)->sum('total') === 1));
});

/** Pengaju dengan profil pada $unit dan satu pengajuan terhitung pada form $formId. */
function submissionFromUnit(int $formId, Organization $unit): FormSubmission
{
    $profile = UserProfile::factory()->create(['organization_id' => $unit->id]);

    return FormSubmission::factory()->create([
        'form_id' => $formId, 'submitted_by' => $profile->user_id, 'status' => Submitted::class,
    ]);
}

/** @return array{0: Organization, 1: Organization} fakultas dan prodi di bawah jurusannya */
function facultyWithDeepProgram(string $name): array
{
    $faculty = Organization::create(['name' => "Fakultas {$name}", 'type' => 'faculty']);
    $department = Organization::create([
        'name' => "Jurusan {$name}", 'type' => 'department', 'parent_id' => $faculty->id,
    ]);
    $prodi = Organization::create([
        'name' => "Prodi {$name}", 'type' => 'study_program', 'parent_id' => $department->id,
    ]);

    return [$faculty, $prodi];
}

/**
 * @param  iterable<array<string, mixed>>  $rows
 * @return array<int, int>
 */
function totalsById(iterable $rows, string $key = 'id', string $total = 'total'): array
{
    return collect($rows)->mapWithKeys(fn ($row) => [$row[$key] => (int) $row[$total]])->sortKeys()->all();
}

test('BR-RPT-06 pengajuan pada form tingkat fakultas dihitung menurut unit pengajunya', function () {
    [$faculty, $prodi] = facultyWithDeepProgram('A');
    $detail = phaseWithSubmissions([]);
    $detail->formAccessControl->update(['organization_id' => $faculty->id]);
    $formId = $detail->formAccessControl->form_id;
    submissionFromUnit($formId, $prodi);
    submissionFromUnit($formId, $faculty);

    $this->actingAs(reportingAdmin())
        ->get(route('admin.stats.form-submission'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totalByFaculty', fn ($rows) => totalsById($rows) === [$faculty->id => 2])
            ->where('totalByProdi', fn ($rows) => totalsById($rows) === [$prodi->id => 1]));
});

test('BR-RPT-06 form dengan dua kontrol akses menghitung setiap pengajuan sekali', function () {
    [$facultyA, $prodiA] = facultyWithDeepProgram('A');
    [, $prodiB] = facultyWithDeepProgram('B');
    $detail = phaseWithSubmissions([]);
    $control = $detail->formAccessControl;
    $control->update(['organization_id' => $prodiA->id]);
    FormAccessControl::create([
        'form_id' => $control->form_id, 'permission' => 'submissions.view-own',
        'organization_id' => $prodiB->id,
    ]);
    submissionFromUnit($control->form_id, $prodiA);

    $this->actingAs(reportingAdmin())
        ->get(route('admin.stats.form-submission'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totalByFaculty', fn ($rows) => totalsById($rows) === [$facultyA->id => 1])
            ->where('totalByProdi', fn ($rows) => totalsById($rows) === [$prodiA->id => 1]));
});

test('BR-RPT-06 statistik fase menggolongkan form menurut tipe unit kontrol aksesnya dan pengajuan menurut pengajunya', function () {
    [$faculty, $prodi] = facultyWithDeepProgram('A');
    $facultyDetail = phaseWithSubmissions([]);
    $facultyDetail->formAccessControl->update(['organization_id' => $faculty->id]);
    $prodiControl = FormAccessControl::create([
        'form_id' => Form::factory()->create()->id, 'permission' => 'submissions.create',
        'organization_id' => $prodi->id,
    ]);
    FormPhaseDetail::factory()->create([
        'form_phase_id' => $facultyDetail->form_phase_id,
        'form_access_control_id' => $prodiControl->id,
    ]);
    submissionFromUnit($facultyDetail->formAccessControl->form_id, $prodi);

    $this->actingAs(reportingAdmin())
        ->get(route('admin.stats.form-phase'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formPhaseFaculty', fn ($rows) => totalsById($rows, 'faculty_id', 'total_forms') === [$faculty->id => 2]
                && totalsById($rows, 'faculty_id', 'total_submissions') === [$faculty->id => 1])
            ->where('formPhaseProdi', fn ($rows) => totalsById($rows, 'study_program_id', 'total_forms') === [$prodi->id => 1]
                && totalsById($rows, 'study_program_id', 'total_submissions') === [$prodi->id => 1]
                && collect($rows)->every(fn ($row) => $row['faculty_id'] === $faculty->id)));
});
