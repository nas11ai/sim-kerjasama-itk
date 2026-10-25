<?php

// tests/Feature/Submission/SubmissionAbilitiesPayloadTest.php
//
// SubmissionAbilitiesData sudah punya test sejak awal tapi nol pemanggil
// produksi: layar memutuskan sendiri kapan tombol Edit muncul dengan
// "pemiliknya dan belum disubmit". Tebakan itu melewatkan dua sumbu yang
// diketahui Policy — arsip periode (BR-SM-10) dan status terminal (BR-SM-05)
// — jadi ia selalu lebih longgar daripada aturannya.

use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Withdrawn;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/** @return array{owner: User, submission: FormSubmission, period: SubmissionPeriod} */
function abilitiesFixture(string $status = Draft::class, bool $forceClosed = false): array
{
    Permission::findOrCreate('submissions.view-all', 'web');

    $period = SubmissionPeriod::factory()->create(['is_force_closed' => $forceClosed]);
    $owner = User::factory()->create();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'status' => $status,
        'is_submitted' => false,
    ]);

    return compact('owner', 'submission', 'period');
}

it('mengirim jawaban Policy ke layar pengajuan', function () {
    $fx = abilitiesFixture();

    $this->actingAs($fx['owner'])
        ->get(route('user.submissions.show', $fx['submission']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/ShowPage')
            ->where('can.view', true)
            ->where('can.update', true)
            ->where('can.submit', true)
            ->where('can.resubmit', false)
            ->where('can.withdraw', false)
            ->etc());
});

it('menutup penyuntingan draft di periode yang sudah diarsipkan', function () {
    // Justru kasus yang lolos tebakan lama: statusnya DRAFT dan
    // `is_submitted` masih false, jadi layar akan menawarkan tombol Edit.
    $fx = abilitiesFixture(forceClosed: true);

    expect($fx['submission']->resolveIsArchived())->toBeTrue();

    $this->actingAs($fx['owner'])
        ->get(route('user.submissions.show', $fx['submission']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.update', false)
            ->etc());
});

it('menutup penyuntingan pada status terminal', function () {
    $fx = abilitiesFixture(Withdrawn::class);

    $this->actingAs($fx['owner'])
        ->get(route('user.submissions.show', $fx['submission']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.update', false)
            ->etc());
});

it('membuka resubmit hanya pada NEEDS_REVISION', function () {
    $fx = abilitiesFixture(NeedsRevision::class);

    $this->actingAs($fx['owner'])
        ->get(route('user.submissions.show', $fx['submission']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.update', true)
            ->where('can.resubmit', true)
            ->etc());
});
