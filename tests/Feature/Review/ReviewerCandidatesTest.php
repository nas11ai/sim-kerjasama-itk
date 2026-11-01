<?php

// tests/Feature/Review/ReviewerCandidatesTest.php
//
// OPERATOR-03-01 — kandidat reviewer dimuat lewat partial reload Inertia (`Inertia::optional`), jadi
// pencarian tetap di server. Penandaannya memakai guard yang sama dengan AssignReviewer.

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Review\ReviewerCandidates;
use App\States\Submission\Submitted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function candidatesOperator(): User
{
    $operator = User::factory()->create();
    $operator->givePermissionTo([
        Permission::findOrCreate('submissions.view-all', 'web'),
        Permission::findOrCreate('reviewers.assign', 'web'),
    ]);

    return $operator;
}

function candidatesSubmission(?int $maxWorkload = null): FormSubmission
{
    return FormSubmission::factory()->create([
        'is_submitted' => true,
        'status' => Submitted::class,
        'submitted_by' => User::factory()->create(['name' => 'Dr. Budi Santoso'])->id,
        'scheme_id' => Scheme::factory()->create(['rules' => array_filter(['max_reviewer_workload' => $maxWorkload])])->id,
    ]);
}

function candidateReviewer(string $name, ?string $nidn = null, array $tenure = []): Reviewer
{
    $user = User::factory()->create(['name' => $name]);
    UserProfile::factory()->create(['user_id' => $user->id, 'nidn' => $nidn]);

    return Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
        ...$tenure,
    ]);
}

/** @return array<string, array<string, mixed>> kandidat per nama */
function candidatesByName(FormSubmission $submission, string $search = ''): array
{
    $rows = [];
    foreach (ReviewerCandidates::run($submission, $search) as $row) {
        $rows[$row->name] = $row->toArray();
    }

    return $rows;
}

it('kandidat tidak dikirim tanpa partial reload', function () {
    candidateReviewer('Dr. Sari Lestari');

    $this->actingAs(candidatesOperator())
        ->get(route('admin.submissions.show', candidatesSubmission()))
        ->assertInertia(fn (Assert $page) => $page->missing('reviewerCandidates'));
});

it('partial reload mengirim kandidat', function () {
    candidateReviewer('Dr. Sari Lestari', '0011223344');
    $submission = candidatesSubmission();

    $this->actingAs(candidatesOperator())
        ->get(route('admin.submissions.show', ['submission' => $submission, 'reviewer_search' => 'sari']))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('reviewerCandidates')
            ->reloadOnly('reviewerCandidates', fn (Assert $reload) => $reload
                ->has('reviewerCandidates', 1)
                ->where('reviewerCandidates.0.name', 'Dr. Sari Lestari')
                ->where('reviewerCandidates.0.nidn', '0011223344')
                ->where('reviewerCandidates.0.activeLoad', 0)
                ->where('reviewerCandidates.0.blockedReason', null)));
});

it('[REV-ASSIGN-04] ketua pengaju ditandai konflik kepentingan', function () {
    $submission = candidatesSubmission();
    Reviewer::factory()->create([
        'user_id' => $submission->submitted_by, 'start_date' => now()->subMonth(), 'end_date' => now()->addMonth(),
    ]);

    expect(candidatesByName($submission)['Dr. Budi Santoso']['blockedReason'])->toBe('Konflik kepentingan');
});

it('[REV-ASSIGN-07] co-investigator ditandai konflik kepentingan', function () {
    $submission = candidatesSubmission();
    $reviewer = candidateReviewer('Dr. Rina Wulandari');
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $reviewer->user->userProfile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    expect(candidatesByName($submission)['Dr. Rina Wulandari']['blockedReason'])->toBe('Konflik kepentingan');
});

it('[REV-ASSIGN-05] reviewer yang sudah ditugaskan ditandai', function () {
    $submission = candidatesSubmission();
    $reviewer = candidateReviewer('Dr. Andi Pratama');
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id, 'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending', 'status' => 'replaced',
    ]);

    expect(candidatesByName($submission)['Dr. Andi Pratama']['blockedReason'])->toBe('Sudah ditugaskan pada siklus ini');
});

it('[REV-ASSIGN-06] beban di batas skema ditandai tinggi tapi tidak diblok', function () {
    $submission = candidatesSubmission(maxWorkload: 1);
    $reviewer = candidateReviewer('Dr. Sari Lestari');
    SubmissionReviewer::create([
        'form_submission_id' => FormSubmission::factory()->create()->id, 'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending', 'status' => 'active',
    ]);

    $row = candidatesByName($submission)['Dr. Sari Lestari'];

    expect($row['activeLoad'])->toBe(1)
        ->and($row['overloaded'])->toBeTrue()
        ->and($row['blockedReason'])->toBeNull();
});

it('[REV-ASSIGN-08] reviewer di luar masa tugas tidak ditawarkan', function () {
    candidateReviewer('Dr. Lama Purna', tenure: ['start_date' => now()->subYear(), 'end_date' => now()->subDay()]);
    candidateReviewer('Dr. Sari Lestari');

    expect(array_keys(candidatesByName(candidatesSubmission())))->toBe(['Dr. Sari Lestari']);
});

it('reviewer yang akunnya nonaktif tidak ditawarkan', function () {
    $reviewer = candidateReviewer('Dr. Nonaktif');
    $reviewer->user->forceFill(['is_active' => false])->save();

    expect(candidatesByName(candidatesSubmission()))->toBe([]);
});

it('pencarian NIDN tidak peka huruf', function () {
    candidateReviewer('Dr. Sari Lestari', 'NIDN-ab12');
    candidateReviewer('Dr. Andi Pratama', '0099');

    expect(array_keys(candidatesByName(candidatesSubmission(), 'AB12')))->toBe(['Dr. Sari Lestari']);
});

it('jumlah query tidak bertambah seiring jumlah kandidat', function () {
    $submission = candidatesSubmission(maxWorkload: 3);
    candidateReviewer('Dr. Satu');
    $count = function () use ($submission): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        ReviewerCandidates::run($submission, '');

        return count(DB::getQueryLog());
    };
    $count(); // pemanasan: skema pengajuan dimuat sekali lalu tersimpan di model
    $withOne = $count();

    foreach (['Dr. Dua', 'Dr. Tiga', 'Dr. Empat'] as $name) {
        candidateReviewer($name);
    }

    expect($count())->toBe($withOne);
});
