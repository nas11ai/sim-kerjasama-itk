<?php

// tests/Feature/Submission/SubmissionViewPagesTest.php
//
// Layar daftar pengajuan per periode dan detail pengajuan. Periode sebuah
// pengajuan adalah FK-nya sendiri (ADR 0004), bukan pivot fase yang boleh
// dimiliki banyak periode.

use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function viewPagesOperator(): User
{
    Permission::findOrCreate('users.manage', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    return $operator;
}

/** @return array{periodA: SubmissionPeriod, periodB: SubmissionPeriod, submission: FormSubmission} */
function viewPagesSharedPhase(string $status = Approved::class): array
{
    $shared = phaseSharedByTwoPeriods('Halaman');
    $submission = FormSubmission::factory()->create([
        'form_id' => $shared['form']->id,
        'form_phase_detail_id' => $shared['detail']->id,
        'submission_period_id' => $shared['periodA']->id,
        'is_submitted' => true,
        'status' => $status,
    ]);

    DB::table('submission_period_phases')->insert([
        'submission_period_id' => $shared['periodA']->id,
        'form_phase_id' => $shared['detail']->form_phase_id,
    ]);

    return ['periodA' => $shared['periodA'], 'periodB' => $shared['periodB'], 'submission' => $submission];
}

it('menghitung statistik periode Operator dari periode milik pengajuan', function () {
    ['periodA' => $periodA, 'periodB' => $periodB] = viewPagesSharedPhase();
    FormSubmission::factory()->create([
        'submission_period_id' => $periodA->id,
        'is_submitted' => true,
        'status' => Rejected::class,
    ]);

    $this->actingAs(viewPagesOperator())
        ->get(route('admin.submissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissionPeriods.data', fn ($rows) => collect($rows)->firstWhere('id', $periodA->id)['total_submissions'] === 2
                && collect($rows)->firstWhere('id', $periodA->id)['approved_submissions'] === 1
                // Yang ditolak sudah diputus, bukan menunggu review.
                && collect($rows)->firstWhere('id', $periodA->id)['pending_review'] === 0
                && collect($rows)->firstWhere('id', $periodB->id)['total_submissions'] === 0));
});

it('mendaftar pengajuan periode Operator hanya dari periodenya sendiri', function () {
    ['periodA' => $periodA, 'periodB' => $periodB, 'submission' => $submission] = viewPagesSharedPhase();
    $operator = viewPagesOperator();

    $this->actingAs($operator)
        ->get(route('admin.submissions.period', $periodA))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.total', 1)
            ->where('submissions.data.0.id', $submission->id));

    $this->actingAs($operator)
        ->get(route('admin.submissions.period', $periodB))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.total', 0));
});

it('menyaring pengajuan periode Operator menurut nama state', function () {
    ['periodA' => $periodA] = viewPagesSharedPhase();
    $operator = viewPagesOperator();

    $this->actingAs($operator)
        ->get(route('admin.submissions.period', [$periodA, 'status' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.total', 1)
            ->where('submissionStatuses', fn ($options) => collect($options)->contains(
                fn ($o) => $o['value'] === 'approved' && $o['label'] === 'Approved',
            )));

    $this->actingAs($operator)
        ->get(route('admin.submissions.period', [$periodA, 'status' => 'rejected']))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.total', 0));
});

it('menyaring pengajuan peneliti per periode dari periode milik pengajuan', function () {
    $fixture = wizardFixture();
    $other = SubmissionPeriod::factory()->create();

    foreach ([$fixture['period'], $other] as $period) {
        DB::table('submission_period_phases')->insert([
            'submission_period_id' => $period->id,
            'form_phase_id' => $fixture['phase']->id,
        ]);
    }

    $submission = FormSubmission::factory()->create([
        'form_id' => $fixture['form']->id,
        'form_phase_detail_id' => $fixture['detail']->id,
        'submission_period_id' => $fixture['period']->id,
        'submitted_by' => $fixture['user']->id,
    ]);

    // Filter periode di daftar memakai FK yang sama, bukan pivot fase yang dipakai dua periode.
    $this->actingAs($fixture['user'])
        ->get(route('user.submissions.index', ['period' => $fixture['period']->id]))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.data.0.id', $submission->id));

    $this->actingAs($fixture['user'])
        ->get(route('user.submissions.index', ['period' => $other->id]))
        ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 0));
});
