<?php

// RESEARCHER-02-11 — jejak perubahan target luaran. Target bagian aggregate pengajuan, jadi
// jejaknya bersubjek pengajuan dan tunduk pada AuditScope (BR-RPT-05), bukan sekadar BR-SM-07.

use App\Actions\FormSubmission\DeleteTargetOutput;
use App\Actions\FormSubmission\SaveTargetOutput;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Submission\TargetOutputHistory;
use App\States\Submission\Approved;
use App\Support\Audit\AuditTrail;

/** @return array{0: User, 1: FormSubmission} */
function targetHistoryFixture(): array
{
    foreach (['publikasi' => 'Publikasi', 'haki' => 'HKI'] as $code => $name) {
        OutputTypeDefinition::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
    }

    $submission = FormSubmission::factory()->create(['status' => Approved::class, 'is_submitted' => true]);

    return [$submission->submittedBy, $submission];
}

it('jejak target memuat tambah, ubah, dan hapus dengan nama tipe', function () {
    [$owner, $submission] = targetHistoryFixture();
    $this->travelTo('2026-01-01 08:00:00');
    $target = SaveTargetOutput::run($submission, $owner, 'publikasi');
    $this->travelTo('2026-02-01 08:00:00');
    SaveTargetOutput::run($submission, $owner, 'haki', $target);
    $this->travelTo('2026-03-01 08:00:00');
    DeleteTargetOutput::run($submission, $target->fresh(), $owner);
    $this->travelBack();

    $history = TargetOutputHistory::run($owner, $submission);

    expect(array_map(fn ($change) => [$change->description, $change->from, $change->to], $history))->toBe([
        ['Target luaran dihapus', 'HKI', null],
        ['Target luaran diubah', 'Publikasi', 'HKI'],
        ['Target luaran disimpan', null, 'Publikasi'],
    ])->and($history[0]->actor)->toBe($owner->name)
        ->and($history[0]->at)->not->toBeNull();
});

it('jejak target memuat target yang sudah dihapus', function () {
    [$owner, $submission] = targetHistoryFixture();
    $target = SaveTargetOutput::run($submission, $owner, 'publikasi');
    DeleteTargetOutput::run($submission, $target, $owner);

    expect($submission->targetOutputs()->count())->toBe(0)
        ->and(TargetOutputHistory::run($owner, $submission))->toHaveCount(2);
});

it('[RPT-AUDIT-05] co-investigator membaca jejak target', function () {
    [$owner, $submission] = targetHistoryFixture();
    SaveTargetOutput::run($submission, $owner, 'publikasi');
    $coInvestigator = User::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $coInvestigator->id])->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    expect(TargetOutputHistory::run($coInvestigator, $submission))->toHaveCount(1);
});

it('[RPT-AUDIT-05] reviewer tidak membaca jejak target', function () {
    [$owner, $submission] = targetHistoryFixture();
    SaveTargetOutput::run($submission, $owner, 'publikasi');
    $user = User::factory()->create();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $user->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    expect($submission->isAccessibleBy($user))->toBeTrue()
        ->and(TargetOutputHistory::run($user, $submission))->toBe([]);
});

it('tidak memuat entri audit pengajuan lain', function () {
    [$owner, $submission] = targetHistoryFixture();
    AuditTrail::record('Pengajuan disubmit', $submission, $owner);
    $other = FormSubmission::factory()->create(['submitted_by' => $owner->id, 'status' => Approved::class]);
    SaveTargetOutput::run($other, $owner, 'publikasi');

    expect(TargetOutputHistory::run($owner, $submission))->toBe([]);
});
