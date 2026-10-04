<?php

// tests/Feature/Review/RemoveEvaluationFormAssignmentTest.php
//
// BR-RPT-09 — menghapus penugasan form adalah perubahan domain, jadi harus berjejak.
// BR-REV-05 — status evaluasi reviewer ikut dihitung ulang karena pekerjaannya berkurang.

use App\Models\FieldType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldResponse;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/** @return array{0: User, 1: ReviewerFormAssignment, 2: ReviewFormResponse} */
function removableAssignmentFixture(string $responseStatus): array
{
    Permission::findOrCreate('users.manage', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'form_phase_detail_id' => $detail->id,
        'status' => UnderReview::class,
    ]);
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    $submissionReviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $submissionReviewer->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => $responseStatus,
    ]);

    $field = ReviewFormField::create([
        'review_evaluation_form_id' => $form->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id,
        'label' => 'Catatan',
        'is_required' => false,
        'order' => 1,
    ]);
    ReviewFormFieldResponse::create([
        'review_form_response_id' => $response->id,
        'review_form_field_id' => $field->id,
        'value' => 'draf',
    ]);

    return [$operator, $assignment, $response];
}

it('menghapus penugasan berikut drafnya, menghitung ulang status, dan mencatat audit — BR-RPT-09', function () {
    [$operator, $assignment, $response] = removableAssignmentFixture('draft');

    $this->actingAs($operator)
        ->delete(route('admin.form-assignments.remove', $assignment))
        ->assertSessionHasNoErrors();

    expect(ReviewerFormAssignment::find($assignment->id))->toBeNull()
        ->and(ReviewFormResponse::find($response->id))->toBeNull()
        ->and(ReviewFormFieldResponse::count())->toBe(0)
        // BR-REV-05 — tak ada form tersisa, jadi tak ada yang ditunggu.
        ->and(SubmissionReviewer::find($assignment->submission_reviewer_id)?->evaluation_status)
        ->toBe('not_required');

    $entry = Activity::inLog(AuditTrail::LOG_NAME)
        ->where('description', 'Penugasan form evaluasi dihapus')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry?->subject_id)->toBe($assignment->id);
});

it('menolak menghapus penugasan yang jawabannya sudah dikirim — BR-REV-05', function () {
    [$operator, $assignment, $response] = removableAssignmentFixture('submitted');

    $this->actingAs($operator)
        ->delete(route('admin.form-assignments.remove', $assignment))
        ->assertSessionHasErrors('error');

    expect(ReviewerFormAssignment::find($assignment->id))->not->toBeNull()
        ->and(ReviewFormResponse::find($response->id))->not->toBeNull()
        ->and(Activity::inLog(AuditTrail::LOG_NAME)
            ->where('description', 'Penugasan form evaluasi dihapus')->count())->toBe(0);
});
