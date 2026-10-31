<?php

// RESEARCHER-02-03 dan 02-12 — isi detail pengajuan bagi penontonnya. Skor, kunci anggaran,
// dan tombol edit diputus backend; layar hanya merender jawaban ini.

use App\Actions\Review\RejectSubmission;
use App\Models\BudgetLineItem;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormFieldResponse;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Submission\ResearcherSubmissionDetail;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;

function detailOf(User $viewer, FormSubmission $submission): array
{
    return ResearcherSubmissionDetail::run($viewer, $submission->fresh());
}

function memberOf(FormSubmission $submission, string $role = ResearchMember::ROLE_MEMBER, string $status = 'active'): User
{
    $user = User::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
        'role' => $role,
        'status' => $status,
    ]);

    return $user;
}

function answerField(FormSubmission $submission, string $type, string $label, ?string $value, int $order = 1): FormField
{
    $field = FormField::create([
        'form_id' => $submission->form_id,
        'field_type_id' => FieldType::firstOrCreate(['name' => $type])->id,
        'label' => $label,
        'is_required' => false,
        'order' => $order,
    ]);
    FormFieldResponse::create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => $value]);

    return $field;
}

it('peran penonton: ketua, co-investigator, anggota, tidak terlibat', function () {
    $submission = FormSubmission::factory()->create();

    $roles = [
        [$submission->submittedBy, 'lead_researcher', 'Ketua'],
        [memberOf($submission, ResearchMember::ROLE_CO_INVESTIGATOR), 'co_investigator', 'Co-Investigator'],
        [memberOf($submission), 'member', 'Anggota'],
        [User::factory()->create(), 'viewer', 'Tidak terlibat'],
    ];

    foreach ($roles as [$viewer, $role, $label]) {
        $detail = detailOf($viewer, $submission)['submission'];
        expect($detail->viewerRole)->toBe($role)->and($detail->viewerRoleLabel)->toBe($label);
    }
});

it('[REV-VIS-04] skor ditahan selama UNDER_REVIEW', function () {
    $submission = FormSubmission::factory()->create(['status' => UnderReview::class, 'is_submitted' => true]);

    $scores = detailOf($submission->submittedBy, $submission)['scores'];

    expect($scores['visible'])->toBeFalse()->and($scores['reason'])->not->toBeNull();
});

it('[REV-VIS-06] skor terbuka dan keputusan tercatat setelah REJECTED', function () {
    $operator = User::factory()->create(['name' => 'Siti Aminah']);
    $submission = FormSubmission::factory()->create([
        'status' => Rejected::class,
        'is_submitted' => true,
        'rejection_reason' => 'Mitra belum memiliki perjanjian kerja sama.',
    ]);
    AuditTrail::record(RejectSubmission::AUDIT_DESCRIPTION, $submission, $operator);

    $detail = detailOf($submission->submittedBy, $submission);

    expect($detail['scores']['visible'])->toBeTrue()
        ->and($detail['decision']->reason)->toBe('Mitra belum memiliki perjanjian kerja sama.')
        ->and($detail['decision']->decidedBy)->toBe('Siti Aminah')
        ->and($detail['decision']->decidedAt)->not->toBeNull();
});

it('penolakan tanpa entri audit tetap membawa alasan', function () {
    $submission = FormSubmission::factory()->create(['status' => Rejected::class, 'rejection_reason' => 'Di luar cakupan.']);

    $decision = detailOf($submission->submittedBy, $submission)['decision'];

    expect($decision->reason)->toBe('Di luar cakupan.')
        ->and($decision->decidedBy)->toBeNull()
        ->and($decision->decidedAt)->toBeNull();
});

it('decision null untuk status selain REJECTED', function () {
    $submission = FormSubmission::factory()->create(['status' => Approved::class]);

    expect(detailOf($submission->submittedBy, $submission)['decision'])->toBeNull();
});

it('[BUD-LOCK-01] anggaran terkunci pada status terminal', function () {
    $submission = FormSubmission::factory()->create(['status' => Approved::class]);

    $budget = detailOf($submission->submittedBy, $submission)['budget'];

    expect($budget->editable)->toBeFalse()->and($budget->lockReason)->toBe('terminal');
});

it('[BUD-WHO-03] anggota biasa hanya membaca anggaran', function () {
    $submission = FormSubmission::factory()->create(['status' => Draft::class]);

    $budget = detailOf(memberOf($submission), $submission)['budget'];

    expect($budget->editable)->toBeFalse()->and($budget->lockReason)->toBe('read_only');
});

it('ketua draft menyunting anggaran dan totalnya dijumlahkan', function () {
    $submission = FormSubmission::factory()->create(['status' => Draft::class]);
    BudgetLineItem::factory()->create(['form_submission_id' => $submission->id, 'volume' => 2, 'unit_price' => 1000, 'total' => 2000]);
    BudgetLineItem::factory()->create(['form_submission_id' => $submission->id, 'volume' => 1, 'unit_price' => 500, 'total' => 500]);

    $budget = detailOf($submission->submittedBy, $submission)['budget'];

    expect($budget->editable)->toBeTrue()
        ->and($budget->lockReason)->toBeNull()
        ->and($budget->items)->toHaveCount(2)
        ->and($budget->total)->toBe(2500);
});

it('editUrl menaut ke wizard', function (string $status) {
    $detail = FormPhaseDetail::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $submission = FormSubmission::factory()->create([
        'status' => $status,
        'submission_period_id' => $period->id,
        'form_phase_detail_id' => $detail->id,
    ]);

    // Revisi tetap lewat wizard sampai RevisionPage lahir di S2c.
    expect(detailOf($submission->submittedBy, $submission)['submission']->editUrl)
        ->toBe(route('user.submissions.wizard', $submission->id))
        ->and(detailOf(memberOf($submission), $submission)['submission']->editUrl)->toBeNull();
})->with([Draft::class, NeedsRevision::class]);

it('pengajuan tanpa periode dan fase tetap terbaca', function () {
    $submission = FormSubmission::factory()->create(['status' => Draft::class]);

    $detail = detailOf($submission->submittedBy, $submission)['submission'];

    expect($detail->editUrl)->toBeNull()->and($detail->period)->toBeNull();
});

it('jawaban select ditampilkan sebagai label opsi', function () {
    $submission = FormSubmission::factory()->create();
    $field = answerField($submission, 'select', 'Bidang', null);
    $option = FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'Energi Terbarukan']);
    FormFieldResponse::where('form_field_id', $field->id)->update(['value' => (string) $option->id]);

    $answers = detailOf($submission->submittedBy, $submission)['answers'];

    expect($answers[0]->label)->toBe('Bidang')->and($answers[0]->value)->toBe('Energi Terbarukan');
});

it('field berkas ditandai isFile dan field pemilih kolom dilewati', function () {
    $submission = FormSubmission::factory()->create();
    answerField($submission, 'file', 'Proposal', 'proposal.pdf', 1);
    answerField($submission, 'scheme_selector', 'Skema', '3', 2);

    $answers = detailOf($submission->submittedBy, $submission)['answers'];

    expect($answers)->toHaveCount(1)->and($answers[0]->isFile)->toBeTrue();
});

it('berkas membawa downloadUrl', function () {
    $submission = FormSubmission::factory()->create();
    $file = answerField($submission, 'file', 'Proposal', 'form-uploads/proposal.pdf', 1);
    answerField($submission, 'text', 'Judul', 'Judul', 2);
    $response = FormFieldResponse::where('form_field_id', $file->id)->sole();

    $answers = detailOf($submission->submittedBy, $submission)['answers'];

    expect($answers[0]->downloadUrl)->toBe(route('user.submissions.files.show', [$submission, $response]))
        ->and($answers[1]->downloadUrl)->toBeNull();
});

it('anggota nonaktif tidak tercantum', function () {
    $submission = FormSubmission::factory()->create();
    memberOf($submission);
    memberOf($submission, ResearchMember::ROLE_MEMBER, 'inactive');

    $members = detailOf($submission->submittedBy, $submission)['members'];

    expect($members)->toHaveCount(2)
        ->and($members[0]->roleLabel)->toBe('Ketua')
        ->and($members[1]->roleLabel)->toBe('Anggota');
});

it('reviewer yang ditugaskan tidak membaca jejak audit pengajuan (BR-RPT-05)', function () {
    $submission = FormSubmission::factory()->create(['status' => UnderReview::class, 'is_submitted' => true]);
    AuditTrail::record('Pengajuan disubmit', $submission, $submission->submittedBy);
    $user = User::factory()->create();
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $user->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    expect($submission->isAccessibleBy($user))->toBeTrue()
        ->and(detailOf($user, $submission)['history'])->toBe([]);
});

it('riwayat memuat entri audit pengajuan ini, terbaru dulu, tanpa entri pengajuan lain', function () {
    $submission = FormSubmission::factory()->create();
    $this->travelTo('2026-01-01 08:00:00');
    AuditTrail::record('Pengajuan disubmit', $submission, $submission->submittedBy);
    $this->travelTo('2026-02-01 08:00:00');
    AuditTrail::record('Penugasan reviewer diproses', $submission);
    AuditTrail::record('Pengajuan disubmit', FormSubmission::factory()->create());
    $this->travelBack();

    $history = detailOf($submission->submittedBy, $submission)['history'];

    expect(array_map(fn ($entry) => $entry->description, $history))
        ->toBe(['Penugasan reviewer diproses', 'Pengajuan disubmit'])
        ->and($history[1]->actor)->toBe($submission->submittedBy->name);
});
