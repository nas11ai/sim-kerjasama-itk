<?php

// tests/Feature/Review/SubmissionStatusTransitionTest.php
//
// `updateSubmissionStatus` menempuh dua jalur: transisi langsung, dan
// loncatan dua langkah lewat UNDER_REVIEW untuk status yang tidak bisa
// dicapai langsung. Pemeriksaan kedua pada jalur loncatan sebelumnya
// memanggil ekspresi yang sama persis dengan pemeriksaan pertama, sehingga
// hasil `false` yang pertama yang berlaku — peringatan statisnya benar dan
// dibungkam. Test ini yang menahannya tetap benar.

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

function statusOperator(): User
{
    Permission::findOrCreate('users.manage', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    return $operator;
}

function submissionInState(string $state): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'status' => $state,
        'is_submitted' => true,
    ]);
}

it('menempuh transisi langsung yang diizinkan', function () {
    $submission = submissionInState(UnderReview::class);

    $this->actingAs(statusOperator())
        ->patch(route('submissions.update-status', $submission), ['status' => 'approved'])
        ->assertSessionHas('success');

    expect($submission->refresh()->status)->toBeInstanceOf(Approved::class);
});

it('meloncat lewat UNDER_REVIEW saat tujuan tidak bisa dicapai langsung', function () {
    // SUBMITTED tidak punya transisi langsung ke APPROVED, tapi punya lewat
    // UNDER_REVIEW. Inilah jalur yang pemeriksaan keduanya dulu mati.
    $submission = submissionInState(Submitted::class);

    expect($submission->status->canTransitionTo(Approved::class))->toBeFalse();

    $this->actingAs(statusOperator())
        ->patch(route('submissions.update-status', $submission), ['status' => 'approved'])
        ->assertSessionHas('success');

    expect($submission->refresh()->status)->toBeInstanceOf(Approved::class);
});

it('menolak transisi yang tidak punya jalur, termasuk lewat loncatan', function () {
    $submission = submissionInState(Draft::class);

    $this->actingAs(statusOperator())
        ->patch(route('submissions.update-status', $submission), ['status' => 'approved'])
        ->assertSessionHas('error');

    expect($submission->refresh()->status)->toBeInstanceOf(Draft::class);
});

it('menolak pengguna yang bukan operator maupun reviewer pengajuan ini', function () {
    $submission = submissionInState(UnderReview::class);

    $this->actingAs(User::factory()->create())
        ->patch(route('submissions.update-status', $submission), ['status' => 'approved'])
        ->assertForbidden();

    expect($submission->refresh()->status)->toBeInstanceOf(UnderReview::class);
});
