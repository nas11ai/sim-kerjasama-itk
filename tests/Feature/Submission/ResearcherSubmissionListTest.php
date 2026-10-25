<?php

// RESEARCHER-02-01 — daftar datar "pengajuan saya": yang diketuai dan yang diikuti sebagai
// anggota aktif. Operator tetap melihat miliknya saja; seluruh sistem ada di layar operator.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Data\View\Submission\SubmissionRowData;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Submission\ResearcherSubmissionList;
use App\States\Submission\Draft;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Spatie\Permission\Models\Permission;

const NO_FILTERS = ['search' => '', 'status' => '', 'period' => ''];

/** @param  array{search?: string, status?: string, period?: string}  $filters */
function myRows(User $viewer, array $filters = []): array
{
    return ResearcherSubmissionList::run($viewer, [...NO_FILTERS, ...$filters])['submissions']->items();
}

function joinAs(User $user, FormSubmission $submission, string $status = 'active'): void
{
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
        'status' => $status,
    ]);
}

it('[SM-ACCESS-01] memuat pengajuan yang ia ketuai', function () {
    $lead = User::factory()->create();
    FormSubmission::factory()->create(['submitted_by' => $lead->id]);

    $rows = myRows($lead);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBeInstanceOf(SubmissionRowData::class)
        ->and($rows[0]->isLead)->toBeTrue();
});

it('[SM-ACCESS-02] memuat pengajuan yang ia ikuti sebagai anggota aktif', function () {
    $member = User::factory()->create();
    joinAs($member, FormSubmission::factory()->create());

    $rows = myRows($member);

    expect($rows)->toHaveCount(1)->and($rows[0]->isLead)->toBeFalse();
});

it('[SM-ACCESS-03] tidak memuat pengajuan peneliti lain', function () {
    FormSubmission::factory()->create();

    expect(myRows(User::factory()->create()))->toBeEmpty();
});

it('[SM-ACCESS-03] tidak memuat pengajuan setelah keanggotaannya nonaktif', function () {
    $former = User::factory()->create();
    joinAs($former, FormSubmission::factory()->create(), 'inactive');

    expect(myRows($former))->toBeEmpty();
});

it('view-all tidak melebarkan daftar', function () {
    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');
    FormSubmission::factory()->create(['submitted_by' => $operator->id]);
    FormSubmission::factory()->count(2)->create();

    expect(myRows($operator))->toHaveCount(1);
});

it('[SM-SUBMIT-03] dua draft ke skema berbeda tampil bersamaan', function () {
    $lead = User::factory()->create();
    foreach (Scheme::factory()->count(2)->create() as $scheme) {
        FormSubmission::factory()->create(['submitted_by' => $lead->id, 'scheme_id' => $scheme->id]);
    }

    $rows = myRows($lead);

    expect($rows)->toHaveCount(2)
        ->and(array_map(fn (SubmissionRowData $row) => $row->status->key, $rows))->toBe(['draft', 'draft']);
});

it('[SM-ARCHIVE-01] draft dari periode tertutup ditandai diarsipkan', function () {
    $lead = User::factory()->create();
    FormSubmission::factory()->create([
        'submitted_by' => $lead->id,
        'submission_period_id' => SubmissionPeriod::factory()->create(['is_force_closed' => true])->id,
    ]);

    expect(myRows($lead)[0]->isArchived)->toBeTrue();
});

it('tanggal submit dari entri audit submit pertama', function () {
    $lead = User::factory()->create();
    $submitted = FormSubmission::factory()->create(['submitted_by' => $lead->id, 'is_submitted' => true]);
    FormSubmission::factory()->create(['submitted_by' => $lead->id, 'updated_at' => now()->subYear()]);

    $this->travelTo('2026-01-12 08:00:00');
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $submitted, $lead);
    $this->travelTo('2026-02-01 08:00:00');
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $submitted, $lead);
    $this->travelBack();

    [$first, $draft] = myRows($lead);

    expect($first->submittedAt)->toStartWith('2026-01-12')
        ->and($draft->submittedAt)->toBeNull();
});

it('mencari judul tanpa peka huruf', function () {
    $lead = User::factory()->create();
    FormSubmission::factory()->create([
        'submitted_by' => $lead->id,
        'form_id' => Form::factory()->create(['title' => 'Biochar Kaltim'])->id,
    ]);
    FormSubmission::factory()->create(['submitted_by' => $lead->id]);

    $rows = myRows($lead, ['search' => 'biochar']);

    expect($rows)->toHaveCount(1)->and($rows[0]->title)->toBe('Biochar Kaltim');
});

it('menyaring status', function () {
    $lead = User::factory()->create();
    FormSubmission::factory()->create(['submitted_by' => $lead->id, 'status' => UnderReview::class]);
    FormSubmission::factory()->create(['submitted_by' => $lead->id, 'status' => Draft::class]);

    $rows = myRows($lead, ['status' => 'under_review']);

    expect($rows)->toHaveCount(1)->and($rows[0]->status->key)->toBe('under_review');
});

it('mengabaikan status yang tidak dikenal', function () {
    $lead = User::factory()->create();
    FormSubmission::factory()->count(2)->create(['submitted_by' => $lead->id]);

    expect(myRows($lead, ['status' => 'x']))->toHaveCount(2);
});

it('menyaring periode', function () {
    $lead = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    FormSubmission::factory()->create(['submitted_by' => $lead->id, 'submission_period_id' => $period->id]);
    FormSubmission::factory()->create(['submitted_by' => $lead->id]);

    expect(myRows($lead, ['period' => (string) $period->id]))->toHaveCount(1);
});

it('menawarkan periode dari pengajuannya sendiri saja', function () {
    $lead = User::factory()->create();
    $mine = SubmissionPeriod::factory()->create(['name' => 'DIPA 2026']);
    FormSubmission::factory()->create(['submitted_by' => $lead->id, 'submission_period_id' => $mine->id]);
    FormSubmission::factory()->create(['submission_period_id' => SubmissionPeriod::factory()->create()->id]);

    expect(ResearcherSubmissionList::run($lead, NO_FILTERS)['periodOptions'])
        ->toBe([['value' => (string) $mine->id, 'label' => 'DIPA 2026']]);
});
