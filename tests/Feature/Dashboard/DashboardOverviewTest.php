<?php

// tests/Feature/Dashboard/DashboardOverviewTest.php
//
// §4.4 K4 — satu dasbor; section bergerbang permission sehingga user dua peran
// melihat gabungannya. Frontend tidak memeriksa nama peran.

use App\Data\View\Dashboard\DashboardSectionData;
use App\Models\Announcement;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\Reviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Dashboard\DashboardOverview;
use App\Support\Audit\AuditTrail;
use Spatie\Permission\Models\Permission;

function ovUser(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

/** @return list<string> */
function ovSectionKeys(User $user): array
{
    return array_map(fn (DashboardSectionData $s) => $s->key, DashboardOverview::run($user)->sections);
}

it('permission reviewer tanpa baris Reviewer tidak memunculkan section', function () {
    expect(ovSectionKeys(ovUser('reviewers.evaluate')))->toBe([]);
});

it('peneliti dan reviewer mendapat dua section', function () {
    $user = ovUser('submissions.view-own', 'reviewers.evaluate');
    Reviewer::factory()->create(['user_id' => $user->id, 'start_date' => now()->subMonth(), 'end_date' => now()->addMonth()]);

    expect(ovSectionKeys($user))->toBe(['researcher', 'reviewer']);
});

it('operator mendapat section manajemen saja', function () {
    expect(ovSectionKeys(ovUser('submissions.view-all', 'users.verify')))->toBe(['management']);
});

it('subtitle = fakultas — program studi bila ada', function () {
    $faculty = Organization::create(['name' => 'Fakultas Teknologi Industri', 'type' => 'faculty']);
    $prodi = Organization::create(['name' => 'Teknik Mesin', 'type' => 'study_program', 'parent_id' => $faculty->id]);
    $user = ovUser('submissions.view-own');
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $prodi->id]);

    expect(DashboardOverview::run($user->refresh())->subtitle)->toBe('Fakultas Teknologi Industri · Teknik Mesin')
        ->and(DashboardOverview::run(ovUser())->subtitle)->toBeNull();
});

it('aktivitas mengikuti AuditScope: peneliti tidak melihat pengajuan orang lain (BR-RPT-05)', function () {
    $budi = ovUser('submissions.view-own');
    $milikBudi = FormSubmission::factory()->create(['submitted_by' => $budi->id]);
    $milikLain = FormSubmission::factory()->create();
    AuditTrail::record('Pengajuan disubmit', $milikBudi, $budi);
    AuditTrail::record('Pengajuan ditolak', $milikLain, User::factory()->create(['name' => 'Operator']));

    $activities = DashboardOverview::run($budi)->activities;

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->description)->toBe('Pengajuan disubmit')
        ->and($activities[0]->actorName)->toBe($budi->name);
});

it('aktivitas maksimal lima, terbaru dulu', function () {
    $operator = ovUser('submissions.view-all');
    $submission = FormSubmission::factory()->create();
    foreach (range(1, 6) as $i) {
        $this->travel(1)->minutes();
        AuditTrail::record("Entri {$i}", $submission, $operator);
    }

    $activities = DashboardOverview::run($operator)->activities;

    expect($activities)->toHaveCount(5)
        ->and($activities[0]->description)->toBe('Entri 6');
});

it('pengumuman terbaru maksimal tiga dari yang terlihat', function () {
    $author = User::factory()->create();
    foreach (range(1, 4) as $i) {
        $this->travel(1)->minutes();
        Announcement::create(['title' => "Info {$i}", 'content' => '<p>Isi</p>', 'type' => 'public', 'created_by' => $author->id]);
    }

    $announcements = DashboardOverview::run(ovUser())->announcements;

    expect($announcements)->toHaveCount(3)
        ->and($announcements[0]->title)->toBe('Info 4');
});

it('user tanpa permission mendapat dasbor kosong tanpa galat', function () {
    $overview = DashboardOverview::run(ovUser());

    expect($overview->sections)->toBe([])
        ->and($overview->pending)->toBeNull()
        ->and($overview->deadlines)->toBe([])
        ->and($overview->activities)->toBe([]);
});
