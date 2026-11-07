<?php

// tests/Feature/Monev/MonevCyclesTest.php
//
// W125 — satu definisi siklus monev milik sebuah induk, dipakai gerbang, layar, dan Action.
// W123/W124 — tim laporan anak adalah tim induknya.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\Override\GrantPhaseOverride;
use App\Data\Request\PhaseOverrideInput;
use App\Enums\DeadlineType;
use App\Enums\MonevCycleState;
use App\Models\AuditEntry;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Monev\MonevCycles;
use App\States\Submission\Withdrawn;
use App\Support\ConflictOfInterestGuard;
use App\Support\MonevAccessGate;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function monevCoInvestigator(FormSubmission $parent): User
{
    $user = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $user->id]);

    ResearchMember::factory()->coInvestigator()->create([
        'form_submission_id' => $parent->id,
        'user_profile_id' => $profile->id,
    ]);

    return $user;
}

function monevSubmittedReport(array $fx, FormPhaseDetail $detail): FormSubmission
{
    $laporan = StartChildSubmission::run($fx['parent'], $detail, $fx['owner']);
    $laporan->update(['is_submitted' => true]);

    return $laporan->fresh();
}

/** @return array<string, MonevCycleState> */
function monevStates(FormSubmission $parent): array
{
    return collect(MonevCycles::run($parent->fresh()))
        ->mapWithKeys(fn ($cycle) => [$cycle->name => $cycle->state])
        ->all();
}

it('siklus induk: detail sesudah detail induk, urut, bernama Monev I/II', function () {
    $fx = monevFixture();

    $ids = MonevCycles::details($fx['parent'])->pluck('id')->all();
    $names = collect(MonevCycles::run($fx['parent']))->pluck('name')->all();

    expect($ids)->toBe([$fx['monev1']->id, $fx['monev2']->id])
        ->and($names)->toBe(['Monev I', 'Monev II']);
});

it('detail pengajuan Form lain di tahap yang sama tidak menghalangi', function () {
    $fx = monevFixture();
    // Detail pengajuan Form lain berbagi tahap (W92) dan berurutan lebih dulu.
    $lain = monevDetail($fx['monev1']->formPhase, 0, 'Batas Submit');

    expect(MonevCycles::details($fx['parent'])->pluck('id')->all())->not->toContain($lain->id)
        ->and(MonevAccessGate::isCycleOpen($fx['parent'], $fx['monev1']))->toBeTrue();
});

it('detail di luar siklus induk ditolak gerbang', function () {
    $fx = monevFixture();
    $asing = FormPhaseDetail::factory()->create(['order' => 9]);

    try {
        MonevAccessGate::assertCycleOpen($fx['parent'], $asing);
        $this->fail('Gerbang meloloskan detail di luar siklus induk.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('form_phase_detail_id')
            ->and($e->errors()['form_phase_detail_id'][0])->toBe('Tahap ini bukan siklus monev pengajuan tersebut.');
    }

    expect(MonevAccessGate::isCycleOpen($fx['parent'], $asing))->toBeFalse();
});

it('keadaan siklus: open, locked, done, submitted', function () {
    $fx = monevFixture();
    StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    $cycles = MonevCycles::run($fx['parent']);

    expect($cycles[0]->state)->toBe(MonevCycleState::Open)
        ->and($cycles[0]->reportId)->not->toBeNull()
        ->and($cycles[0]->blockedReason)->toBeNull()
        ->and($cycles[1]->state)->toBe(MonevCycleState::Locked)
        ->and($cycles[1]->blockedReason)->toBe('Selesaikan Laporan Monev I lebih dulu sebelum mengisi Monev II.')
        ->and($cycles[1]->reportId)->toBeNull();

    $other = monevFixture();
    monevSubmittedReport($other, $other['monev1']);

    expect(monevStates($other['parent']))->toBe([
        'Monev I' => MonevCycleState::Done,
        'Monev II' => MonevCycleState::Open,
    ]);
});

it('[MON-CYCLE-04] siklus dengan ReviewSummary open berstatus submitted, bukan done', function () {
    $fx = monevFixture(monev1NeedsReview: true);
    $laporan = monevSubmittedReport($fx, $fx['monev1']);

    $reviewer = Reviewer::factory()->create(['user_id' => verifiedResearcher()->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $laporan->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'active',
        'evaluation_status' => 'completed',
    ]);
    ReviewSummary::create([
        'form_submission_id' => $laporan->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Perlu perbaikan metode.',
    ]);

    expect(monevStates($fx['parent']))->toBe([
        'Monev I' => MonevCycleState::Submitted,
        'Monev II' => MonevCycleState::Locked,
    ]);
});

it('[MON-CYCLE-10] induk tarikan membekukan siklus yang belum disubmit', function () {
    $fx = monevFixture();
    monevSubmittedReport($fx, $fx['monev1']);

    $fx['parent']->status->transitionTo(Withdrawn::class);
    $cycles = MonevCycles::run($fx['parent']->fresh());

    expect($cycles[0]->state)->toBe(MonevCycleState::Done)
        ->and($cycles[1]->state)->toBe(MonevCycleState::Frozen)
        ->and($cycles[1]->blockedReason)
        ->toBe('Pengajuan ini sudah ditarik. Siklus monevnya dibekukan dan tidak menerima laporan baru.');
});

it('siklus lewat batas berstatus closed', function () {
    $fx = monevFixture();

    $this->travelTo(now()->addMonths(2));
    $cycles = MonevCycles::run($fx['parent']);

    expect($cycles[0]->state)->toBe(MonevCycleState::Closed)
        ->and($cycles[0]->blockedReason)
        ->toBe('Batas waktu Monev I sudah lewat. Hubungi Operator LPPM bila membutuhkan perpanjangan.')
        ->and($cycles[0]->deadline)->toBe($fx['monev1']->submissionDate->datetime->toIso8601String());
});

it('[MON-CYCLE-09] override yang berlaku membuat siklus tertutup kembali open', function () {
    $fx = monevFixture();
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    $this->travelTo(now()->addMonths(2));
    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = verifiedResearcher();
    $operator->givePermissionTo('submissions.view-all');

    GrantPhaseOverride::run(
        $laporan,
        $fx['monev1']->fresh(),
        $operator,
        new PhaseOverrideInput('Kendala jaringan saat batas monev berjalan', now()->addWeek()->toDateTimeString()),
    );

    expect(monevStates($fx['parent'])['Monev I'])->toBe(MonevCycleState::Open);
});

it('laporan dimulai anggota tetap milik ketua induk', function () {
    $fx = monevFixture();
    $ani = monevCoInvestigator($fx['parent']);

    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $ani);
    $audit = AuditEntry::query()->where('subject_id', $laporan->id)
        ->where('description', 'Laporan tahap dimulai')->sole();

    expect($laporan->submitted_by)->toBe($fx['owner']->id)
        ->and($audit->causer_id)->toBe($ani->id);
});

it('anggota aktif induk mengakses laporan anak', function () {
    $fx = monevFixture();
    $ani = monevCoInvestigator($fx['parent']);
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    expect($laporan->teamSubmissionId())->toBe($fx['parent']->id)
        ->and($fx['parent']->teamSubmissionId())->toBe($fx['parent']->id)
        ->and($laporan->isAccessibleBy($ani))->toBeTrue()
        ->and(SubmissionVisibility::ownedOrJoinedBy($ani)->pluck('id')->all())->toContain($laporan->id);
});

it('anggota aktif induk berkonflik dengan laporan anak', function () {
    $fx = monevFixture();
    $ani = monevCoInvestigator($fx['parent']);
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    expect(ConflictOfInterestGuard::conflicts($ani, $laporan))->toBeTrue();
});

it('reviewer induk tidak berkonflik dengan laporan anak', function () {
    $fx = monevFixture();
    $reviewerUser = verifiedResearcher();
    $reviewer = Reviewer::factory()->create(['user_id' => $reviewerUser->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $fx['parent']->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'active',
        'evaluation_status' => 'completed',
    ]);
    $laporan = StartChildSubmission::run($fx['parent'], $fx['monev1'], $fx['owner']);

    // BR-MON-07 — reviewer induk justru saran pertama penilai laporan.
    expect(ConflictOfInterestGuard::conflicts($reviewerUser, $laporan))->toBeFalse();
});

it('nama siklus monev memakai angka romawi sampai X', function () {
    expect(DeadlineType::cycleName(1))->toBe('Monev I')
        ->and(DeadlineType::cycleName(11))->toBe('Monev 11')
        ->and(DeadlineType::Submit->label(2))->toBe('Batas Laporan Monev II');
});
