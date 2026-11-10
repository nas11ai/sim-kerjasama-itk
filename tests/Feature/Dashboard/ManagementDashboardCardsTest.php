<?php

// tests/Feature/Dashboard/ManagementDashboardCardsTest.php
//
// SHARED-02-03/04 — kartu "Manajemen", tiap kartu bergerbang permission-nya
// sendiri (§4.4 K1). Angka pengajuan menghitung baris yang juga tampil di
// OperatorSubmissionList (W42): draft tidak terlihat oleh Operator di mana pun.

use App\Data\View\Dashboard\KpiCardData;
use App\Enums\VerificationStatus;
use App\Models\ExportJob;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Dashboard\ManagementDashboardCards;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Reporting\ExportType;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function mdUser(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

/** @return array<string, KpiCardData> */
function mdCards(User $user): array
{
    $section = ManagementDashboardCards::run($user)['section'];

    return $section === null ? [] : collect($section->cards)->keyBy('key')->all();
}

function mdSubmission(string $status, bool $submitted = true, ?int $parent = null): FormSubmission
{
    return FormSubmission::factory()->create([
        'status' => $status,
        'is_submitted' => $submitted,
        'parent_submission_id' => $parent,
    ]);
}

function mdExport(User $requester, string $status, ?string $completedAt): ExportJob
{
    return ExportJob::create([
        'requested_by' => $requester->id,
        'type' => ExportType::SUBMISSIONS,
        'status' => $status,
        'row_count' => 1,
        'attempts' => 1,
        'completed_at' => $completedAt,
    ]);
}

it('tanpa permission manajemen tidak ada section', function () {
    $result = ManagementDashboardCards::run(User::factory()->create());

    expect($result['section'])->toBeNull()
        ->and($result['pending'])->toBeNull();
});

it('submissions.view-all memberi tiga kartu pengajuan; draft dan laporan anak tidak terhitung', function () {
    $operator = mdUser('submissions.view-all');
    mdSubmission(Submitted::class);
    $parent = mdSubmission(UnderReview::class);
    mdSubmission(Rejected::class);
    mdSubmission(Draft::class, submitted: false);
    mdSubmission(Submitted::class, parent: $parent->id);

    $cards = mdCards($operator);

    expect(array_keys($cards))->toBe(['active', 'awaitingReviewer', 'awaitingDecision'])
        ->and($cards['active']->value)->toBe('2')
        ->and($cards['active']->meta)->toBe('Semua skema')
        ->and($cards['awaitingReviewer']->value)->toBe('1')
        ->and($cards['awaitingReviewer']->meta)->toBe('Status Disubmit')
        ->and($cards['awaitingReviewer']->href)->toBe(route('admin.submissions.index', ['status' => 'submitted']));
});

it('users.verify memberi kartu antrian NIDN', function () {
    UserProfile::factory()->create(['verification_status' => VerificationStatus::Pending->value]);
    UserProfile::factory()->create(['verification_status' => VerificationStatus::Active->value]);

    $cards = mdCards(mdUser('users.verify'));

    expect(array_keys($cards))->toBe(['awaitingVerification'])
        ->and($cards['awaitingVerification']->value)->toBe('1')
        ->and($cards['awaitingVerification']->meta)->toBe('Antrian NIDN')
        ->and($cards['awaitingVerification']->href)->toBe(route('admin.verifications.index'));
});

it('Export Selesai hanya ekspor milik viewer yang selesai minggu ini (BR-RPT-07)', function () {
    $this->travelTo(now()->startOfWeek()->addDays(2));
    $operator = mdUser('reporting.export');
    mdExport($operator, ExportJob::STATUS_COMPLETED, now()->subDay()->toDateTimeString());
    mdExport($operator, ExportJob::STATUS_COMPLETED, now()->subWeek()->toDateTimeString());
    mdExport($operator, ExportJob::STATUS_FAILED, null);
    mdExport(mdUser('reporting.export'), ExportJob::STATUS_COMPLETED, now()->toDateTimeString());

    $cards = mdCards($operator);

    expect(array_keys($cards))->toBe(['exports'])
        ->and($cards['exports']->value)->toBe('1')
        ->and($cards['exports']->meta)->toBe('Minggu ini')
        ->and($cards['exports']->href)->toBe(route('reporting.exports.index'));
});

it('users.manage memberi kartu User Aktif', function () {
    $admin = mdUser('users.manage');
    User::factory()->create(['is_active' => false]);

    $cards = mdCards($admin);

    expect(array_keys($cards))->toBe(['activeUsers'])
        ->and($cards['activeUsers']->value)->toBe((string) User::query()->where('is_active', true)->count())
        ->and($cards['activeUsers']->meta)->toBe('Semua role');
});

it('operator di sistem kosong mendapat angka nol', function () {
    $cards = mdCards(mdUser('submissions.view-all', 'users.verify', 'reporting.export'));

    expect(array_map(fn (KpiCardData $c) => $c->value, array_values($cards)))->toBe(['0', '0', '0', '0', '0']);
});

it('banner antrian untuk pemegang reviewers.assign bila ada yang menunggu', function () {
    $operator = mdUser('submissions.view-all', 'reviewers.assign', 'users.verify');
    expect(ManagementDashboardCards::run($operator)['pending'])->toBeNull();

    mdSubmission(Submitted::class);
    UserProfile::factory()->create(['verification_status' => VerificationStatus::Pending->value]);
    UserProfile::factory()->create(['verification_status' => VerificationStatus::Pending->value]);

    $pending = ManagementDashboardCards::run($operator)['pending'];

    expect($pending?->awaitingReviewer)->toBe(1)
        ->and($pending?->awaitingVerification)->toBe(2);
});

it('[REV-OUTCOME-21] kartu Menunggu Keputusan menghitung pengajuan siap diputuskan', function () {
    Notification::fake();
    readyForDecision();
    $running = outcomeSubmission();
    reviewerOn($running, 'completed');

    $card = mdCards(mdUser('submissions.view-all'))['awaitingDecision'];

    expect($card->value)->toBe('1')
        ->and($card->label)->toBe('Menunggu Keputusan')
        ->and($card->href)->toBe(route('admin.submissions.index', ['decision' => 'awaiting']));
});
