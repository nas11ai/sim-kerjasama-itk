<?php

// tests/Feature/FormEngine/ChildSubmissionGateTest.php
//
// BR-FE-04 — laporan monev dan kelengkapan berkas adalah child FormSubmission,
// dan hanya boleh lahir dari induk yang sudah APPROVED.
//
// Sebelum irisan ini, `parent_submission_id` hanya punya index unik dan tidak
// ada satu pun jalur yang membuat child — jadi tidak ada tempat aturannya bisa
// ditegakkan. Yang ada baru schema, belum perilaku.

use App\Actions\FormSubmission\StartChildSubmission;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Illuminate\Validation\ValidationException;

/** @return array{parent: FormSubmission, detail: FormPhaseDetail, user: User} */
function childFixture(string $status): array
{
    $fx = wizardFixture();

    $parent = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => $status,
        'is_submitted' => $status !== Draft::class,
    ]);

    // W125 — laporan lahir di siklus induk: detail sesudah detail induk dalam tahap yang sama.
    $cycle = monevDetail($fx['detail']->formPhase, $fx['detail']->order + 1, 'Batas Laporan Monev I');

    return ['parent' => $parent, 'detail' => $cycle, 'user' => $fx['user']];
}

it('[FE-CHILD-01] child tidak bisa dibuat dari induk yang belum disetujui', function (string $status) {
    $fx = childFixture($status);

    expect(fn () => StartChildSubmission::run($fx['parent'], $fx['detail'], $fx['user']))
        ->toThrow(ValidationException::class);

    expect(FormSubmission::whereNotNull('parent_submission_id')->count())->toBe(0);
})->with([
    Draft::class,
    Submitted::class,
    UnderReview::class,
    NeedsRevision::class,
    Resubmitted::class,
    Rejected::class,
    Withdrawn::class,
]);

it('[FE-CHILD-02] child bisa dibuat dari induk yang sudah disetujui', function () {
    $fx = childFixture(Approved::class);

    $child = StartChildSubmission::run($fx['parent'], $fx['detail'], $fx['user']);

    expect($child->parent_submission_id)->toBe($fx['parent']->id)
        ->and($child->status)->toBeInstanceOf(Draft::class)
        ->and($child->submission_period_id)->toBe($fx['parent']->submission_period_id);
});

// W89 — menonaktifkan Form menghentikan pengajuan baru (BR-FE-19), bukan kewajiban
// melapor dari kegiatan yang sudah disetujui.
it('laporan tahap tetap bisa dimulai walau Form-nya dinonaktifkan', function () {
    $fx = childFixture(Approved::class);
    $fx['detail']->formAccessControl?->form?->update(['is_active' => false]);

    $child = StartChildSubmission::run($fx['parent'], $fx['detail'], $fx['user']);

    expect($child->parent_submission_id)->toBe($fx['parent']->id)
        ->and($child->form?->is_active)->toBeFalse();
});

// W89 / K9a — menonaktifkan tahap menghentikan pengajuan baru, bukan laporan monev.
it('laporan tahap tetap bisa dimulai walau tahapnya dinonaktifkan', function () {
    $fx = childFixture(Approved::class);
    $fx['detail']->formPhase->update(['is_active' => false]);

    $child = StartChildSubmission::run($fx['parent'], $fx['detail'], $fx['user']);

    expect($child->parent_submission_id)->toBe($fx['parent']->id);
});

it('[FE-CHILD-02] jalur HTTP menolak induk yang belum disetujui', function () {
    $fx = childFixture(UnderReview::class);

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.children.store', $fx['parent']), [
            'form_phase_detail_id' => $fx['detail']->id,
        ])
        ->assertSessionHasErrors('parent_submission_id');

    expect(FormSubmission::whereNotNull('parent_submission_id')->count())->toBe(0);
});

it('[FE-CHILD-03] laporan tetap ada setelah induk ditarik', function () {
    $fx = childFixture(Approved::class);

    $child = StartChildSubmission::run($fx['parent'], $fx['detail'], $fx['user']);

    $fx['parent']->status->transitionTo(Withdrawn::class);

    // Penarikan membekukan kegiatannya, bukan menghapus jejaknya. Laporan yang
    // sudah dikirim tetap terbaca sebagai data historis.
    expect(FormSubmission::find($child->id))->not->toBeNull()
        ->and($child->fresh()->parent_submission_id)->toBe($fx['parent']->id)
        ->and($fx['parent']->fresh()->status)->toBeInstanceOf(Withdrawn::class);
});

it('memanggil dua kali memakai ulang laporan yang sama, bukan menabrak index unik', function () {
    $fx = childFixture(Approved::class);

    $pertama = StartChildSubmission::run($fx['parent'], $fx['detail'], $fx['user']);
    $kedua = StartChildSubmission::run($fx['parent'], $fx['detail'], $fx['user']);

    expect($kedua->id)->toBe($pertama->id)
        ->and(FormSubmission::whereNotNull('parent_submission_id')->count())->toBe(1);
});
