<?php

// tests/Feature/FormEngine/DeadlineOverrideTest.php
//
// BR-FE-27 (K8e) — tenggat tetap bisa dilampaui oleh pemegang wewenangnya. Override per
// pengajuan dibuat hanya oleh pemegang `submissions.view-all`, dan membuka tenggat yang sedang
// menggerbangi aktor pada detail itu: Batas Submit, Batas Revisi, atau Batas Evaluasi reviewer.
// Penutupan paksa tidak pernah ditembus (BR-SCH-07).

use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use App\Models\User;
use App\States\Submission\NeedsRevision;
use App\Support\PhaseAccessGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function grantDeadlineOverride(FormSubmission $submission, FormPhaseDetail $detail): FormSubmissionOverride
{
    return FormSubmissionOverride::create([
        'form_submission_id' => $submission->id,
        'form_phase_detail_id' => $detail->id,
        'granted_by' => User::factory()->create()->id,
        'reason' => 'Reviewer sakit.',
        'expires_at' => Carbon::parse('2026-03-31 23:59:59'),
        'is_active' => true,
    ]);
}

it('[FE-OVERRIDE-12] override membuka evaluasi reviewer yang lewat Batas Evaluasi', function () {
    $fx = reviewedDetailFixture('2026-03-01 23:59:59', '2026-03-20 23:59:59', '2026-03-15 23:59:59');
    $this->travelTo(Carbon::parse('2026-03-25 09:00:00'));

    expect($fx['reviewer']->can('evaluate', $fx['assignment']->fresh()))->toBeFalse();

    grantDeadlineOverride($fx['submission'], $fx['detail']);

    expect($fx['reviewer']->can('evaluate', $fx['assignment']->fresh()))->toBeTrue();
});

it('[FE-OVERRIDE-13] override membuka penyuntingan NEEDS_REVISION yang lewat Batas Revisi', function () {
    $fx = reviewedDetailFixture('2026-03-01 23:59:59', '2026-03-20 23:59:59', '2026-03-15 23:59:59');
    $fx['submission']->forceFill(['status' => NeedsRevision::class])->save();
    $this->travelTo(Carbon::parse('2026-03-20 09:00:00'));

    expect(PhaseAccessGate::isOpenFor($fx['submission']->fresh(), $fx['detail']))->toBeFalse();

    grantDeadlineOverride($fx['submission'], $fx['detail']);

    expect(PhaseAccessGate::isOpenFor($fx['submission']->fresh(), $fx['detail']))->toBeTrue();
});

it('[FE-OVERRIDE-11] penutupan paksa mengalahkan override evaluasi', function () {
    $fx = reviewedDetailFixture('2026-03-01 23:59:59', '2026-03-20 23:59:59', '2026-03-15 23:59:59');
    $this->travelTo(Carbon::parse('2026-03-25 09:00:00'));
    grantDeadlineOverride($fx['submission'], $fx['detail']);

    $fx['period']->update(['is_force_closed' => true]);

    expect($fx['detail']->fresh()->isWithinDeadline($fx['period']->fresh(), DeadlineType::Evaluation))->toBeFalse()
        ->and(PhaseAccessGate::isOpenFor($fx['submission']->fresh(), $fx['detail']->fresh()))->toBeFalse();
});

it('[FE-OVERRIDE-14] hanya pemegang submissions.view-all yang bisa membuat override', function (string $actor) {
    Notification::fake();
    $fx = reviewedDetailFixture('2026-03-01 23:59:59', '2026-03-20 23:59:59', '2026-03-15 23:59:59');
    $this->travelTo(Carbon::parse('2026-03-25 09:00:00'));

    $user = match ($actor) {
        'peneliti pemilik' => $fx['submission']->submittedBy,
        'reviewer' => $fx['reviewer'],
        'pemegang periods.manage saja' => User::factory()->create()->givePermissionTo(Permission::findOrCreate('periods.manage', 'web')),
        'pemegang submissions.view-all' => User::factory()->create()->givePermissionTo(Permission::findOrCreate('submissions.view-all', 'web')),
    };

    $response = $this->actingAs($user)->post(route('admin.submissions.overrides.store', $fx['submission']), [
        'form_phase_detail_id' => $fx['detail']->id,
        'reason' => 'Reviewer sakit.',
        'expires_at' => '2026-03-31 23:59:59',
    ]);

    if ($actor === 'pemegang submissions.view-all') {
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect(FormSubmissionOverride::query()->count())->toBe(1);

        return;
    }

    $response->assertForbidden();
    expect(FormSubmissionOverride::query()->count())->toBe(0);
})->with(['peneliti pemilik', 'reviewer', 'pemegang periods.manage saja', 'pemegang submissions.view-all']);
