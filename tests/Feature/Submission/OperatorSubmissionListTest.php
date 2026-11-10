<?php

// OPERATOR-02-01 — daftar datar seluruh pengajuan terkirim bagi pemegang
// submissions.view-all (BR-SM-07). Draft dan laporan monev tidak terdaftar.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function listOperator(): User
{
    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    return $operator;
}

/** @param  array<string, mixed>  $attributes */
function sentSubmission(array $attributes = []): FormSubmission
{
    return FormSubmission::factory()->create([
        'is_submitted' => true,
        'status' => Submitted::class,
        ...$attributes,
    ]);
}

function assignActiveReviewer(FormSubmission $submission, string $status = 'active'): void
{
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => $status,
    ]);
}

/** @return list<int> */
function listedIds(array $query = []): array
{
    $ids = [];
    test()->actingAs(listOperator())
        ->get(route('admin.submissions.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$ids) {
            $ids = array_column($page->toArray()['props']['submissions']['data'], 'id');
        });

    return $ids;
}

it('[SM-ACCESS-04] pemegang submissions.view-all membuka Submission/ManageIndexPage', function () {
    $lead = User::factory()->create(['name' => 'Dr. Budi Santoso']);
    UserProfile::factory()->create(['user_id' => $lead->id]);
    $submission = sentSubmission(['submitted_by' => $lead->id, 'status' => UnderReview::class]);
    assignActiveReviewer($submission);
    assignActiveReviewer($submission);
    assignActiveReviewer($submission, 'replaced');

    $this->actingAs(listOperator())
        ->get(route('admin.submissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/ManageIndexPage')
            ->has('submissions.data', 1)
            ->where('submissions.data.0.leadName', 'Dr. Budi Santoso')
            ->where('submissions.data.0.organization', $lead->userProfile->organization->name)
            ->where('submissions.data.0.reviewerCount', 2)
            ->where('submissions.data.0.status.key', 'under_review')
            ->has('statusOptions')
            ->has('periodOptions')
            ->has('schemeOptions')
            ->where('filters', ['search' => '', 'status' => '', 'period' => '', 'scheme' => '', 'year' => '', 'decision' => '']));
});

it('peneliti tanpa view-all ditolak', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.submissions.index'))
        ->assertForbidden();
});

it('draft tidak terdaftar', function () {
    $sent = sentSubmission();
    FormSubmission::factory()->create();

    expect(listedIds())->toBe([$sent->id]);
});

it('laporan monev tidak terdaftar', function () {
    $parent = sentSubmission(['status' => Approved::class]);
    sentSubmission(['parent_submission_id' => $parent->id]);

    expect(listedIds())->toBe([$parent->id]);
});

it('saring status, periode, dan skema', function () {
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();
    $approved = sentSubmission(['status' => Approved::class]);
    $inPeriod = sentSubmission(['submission_period_id' => $period->id]);
    $inScheme = sentSubmission(['scheme_id' => $scheme->id]);

    expect(listedIds(['status' => 'approved']))->toBe([$approved->id])
        ->and(listedIds(['period' => (string) $period->id]))->toBe([$inPeriod->id])
        ->and(listedIds(['scheme' => (string) $scheme->id]))->toBe([$inScheme->id]);
});

it('pencarian judul atau ketua tidak peka huruf', function () {
    $byLead = sentSubmission(['submitted_by' => User::factory()->create(['name' => 'Dr. Budi Santoso'])->id]);
    $byTitle = sentSubmission(['form_id' => Form::factory()->create(['title' => 'Kajian BUDIDAYA Rumput Laut'])->id]);
    sentSubmission();

    expect(listedIds(['search' => 'budi']))->toEqualCanonicalizing([$byLead->id, $byTitle->id]);
});

it('tanggal submit dari jejak audit', function () {
    $submission = sentSubmission();
    $this->travelTo('2026-01-12 08:00:00');
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $submission, $submission->submittedBy);
    $this->travelTo('2026-02-01 08:00:00');
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $submission, $submission->submittedBy);
    $this->travelBack();

    $this->actingAs(listOperator())
        ->get(route('admin.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.data.0.submittedAt', fn (string $at) => str_starts_with($at, '2026-01-12')));
});

// W149 — drill-down statistik membuka daftar yang barisnya sama dengan angkanya.
it('filter tahun memakai tahun submit pertama', function () {
    $resubmittedIn2026 = sentSubmission();
    $firstIn2026 = sentSubmission();
    $this->travelTo('2025-11-03 08:00:00');
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $resubmittedIn2026, $resubmittedIn2026->submittedBy);
    $this->travelTo('2026-02-01 08:00:00');
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $resubmittedIn2026, $resubmittedIn2026->submittedBy);
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $firstIn2026, $firstIn2026->submittedBy);
    $this->travelBack();

    expect(listedIds(['year' => '2025']))->toBe([$resubmittedIn2026->id])
        ->and(listedIds(['year' => '2026']))->toBe([$firstIn2026->id])
        ->and(listedIds(['year' => 'abc']))->toHaveCount(2);

    $this->actingAs(listOperator())
        ->get(route('admin.submissions.index', ['year' => '2025']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.year', '2025')
            ->where('yearOptions', [['value' => '2026', 'label' => '2026'], ['value' => '2025', 'label' => '2025']]));
});

it('opsi status tidak menawarkan draft', function () {
    $this->actingAs(listOperator())
        ->get(route('admin.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusOptions', fn ($options) => !collect($options)->contains('value', 'draft')));
});

it('[REV-OUTCOME-21] penyaring Menunggu keputusan hanya memuat pengajuan siap diputuskan', function () {
    Notification::fake();
    $ready = readyForDecision();
    $running = outcomeSubmission();
    reviewerOn($running, 'completed');
    // Induk APPROVED; laporannya SUBMITTED tapi laporan monev tidak pernah diputuskan.
    $fx = monevFixture();
    FormSubmission::factory()->create([
        'parent_submission_id' => $fx['parent']->id,
        'form_phase_detail_id' => $fx['monev1']->id,
        'submitted_by' => $fx['owner']->id,
        'status' => Submitted::class,
        'is_submitted' => true,
    ]);

    $this->actingAs(listOperator())
        ->get(route('admin.submissions.index', ['decision' => 'awaiting']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.decision', 'awaiting')
            ->has('submissions.data', 1)
            ->where('submissions.data.0.id', $ready->id));
});

it('nilai penyaring keputusan yang tidak dikenal diabaikan', function () {
    sentSubmission();

    $this->actingAs(listOperator())
        ->get(route('admin.submissions.index', ['decision' => 'entah']))
        ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 1));
});
