<?php

// tests/Feature/Review/FormAssignmentsPayloadTest.php
//
// Endpoint JSON penugasan form mengelompokkan per reviewer. Memilih kolom
// reviewer tanpa kunci relasinya membuat `reviewer` null, sehingga semua
// penugasan jatuh ke satu kelompok tanpa nama dan identitas penilainya hilang.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

it('mengelompokkan penugasan form per reviewer beserta identitasnya', function () {
    Permission::findOrCreate('users.manage', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'form_phase_detail_id' => $detail->id,
        'status' => UnderReview::class,
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $reviewers = collect(['Penilai Satu', 'Penilai Dua'])->map(function (string $name) use ($submission, $form) {
        $reviewer = Reviewer::factory()->create([
            'user_id' => User::factory()->create(['name' => $name])->id,
        ]);

        $assignment = SubmissionReviewer::create([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'evaluation_status' => 'pending',
            'status' => 'active',
        ]);

        ReviewerFormAssignment::create([
            'submission_reviewer_id' => $assignment->id,
            'review_evaluation_form_id' => $form->id,
            'is_required' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        return $reviewer;
    });

    [$first, $second] = $reviewers->all();

    $this->actingAs($operator)
        ->getJson(route('admin.submissions.form-assignments', $submission))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath($first->id.'.0.submission_reviewer.reviewer.user.name', 'Penilai Satu')
        ->assertJsonPath($second->id.'.0.submission_reviewer.reviewer.user.name', 'Penilai Dua')
        ->assertJsonPath($first->id.'.0.submission_reviewer.reviewer.reviewer_type', 'internal');
});
