<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Queries\Review\EvaluationDueDate;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reviewer memulai sendiri satu form evaluasi dari detail tahap pengajuannya. Penugasan yang
 * sudah ada dipakai ulang; yang baru menyalin syarat wajib dari pemasangannya di langkah itu.
 *
 * @method static ReviewerFormAssignment run(FormSubmission $submission, ReviewEvaluationForm $form, User $user)
 */
class StartEvaluationForm
{
    use AsAction;

    /**
     * @throws AuthorizationException
     */
    public function handle(FormSubmission $submission, ReviewEvaluationForm $form, User $user): ReviewerFormAssignment
    {
        $submissionReviewer = $this->activeAssignmentOf($submission, $user);

        $step = $submission->formPhaseDetail?->stepEvaluationForms()->where('review_evaluation_form_id', $form->id)->first()
            ?? throw new AuthorizationException('Formulir evaluasi ini tidak tersedia untuk pengajuan ini.');

        $existing = $submissionReviewer->reviewerFormAssignments()->where('review_evaluation_form_id', $form->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        // BR-FE-22 — form yang dinonaktifkan berhenti ditawarkan untuk pekerjaan baru.
        if (!$step->is_active) {
            throw new AuthorizationException('Formulir evaluasi ini tidak tersedia untuk pengajuan ini.');
        }

        $assignment = $submissionReviewer->reviewerFormAssignments()->create([
            'review_evaluation_form_id' => $form->id,
            'is_required' => $step->is_required,
            'assigned_at' => now(),
            'due_date' => EvaluationDueDate::run($submission),
            'is_active' => true,
        ]);

        // BR-REV-05 — pekerjaannya bertambah, jadi statusnya ikut dihitung ulang.
        RecalculateEvaluationStatus::run($submissionReviewer);

        AuditTrail::record('Form evaluasi dimulai reviewer', $assignment, $user);

        return $assignment;
    }

    /**
     * @throws AuthorizationException
     */
    private function activeAssignmentOf(FormSubmission $submission, User $user): SubmissionReviewer
    {
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        if ($reviewer === null) {
            throw new AuthorizationException('Anda tidak terdaftar sebagai penilai.');
        }

        return ActiveReviewerAssignment::run($submission, $reviewer->id)
            ?? throw new AuthorizationException('Anda tidak ditugaskan sebagai penilai untuk pengajuan ini.');
    }
}
