<?php

namespace App\Http\Controllers;

use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\ReviewSummaryAttachment;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Services\EmailNotificationService;
use App\SubmissionStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    protected $emailService;

    public function __construct(EmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    // Enhanced assign reviewers with evaluation forms
    public function assignReviewers(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'exists:reviewers,id',
            'auto_assign_forms' => 'boolean', // NEW: control auto-assignment
        ]);

        $newlyAssignedReviewers = [];

        DB::transaction(function () use ($request, $submission, &$newlyAssignedReviewers) {
            $formPhaseDetail = $submission->getFormPhaseDetail();
            $hasEvaluationForms = $formPhaseDetail && $formPhaseDetail->hasReviewEvaluationForms();

            foreach ($request->reviewer_ids as $reviewerId) {
                // Create or get submission reviewer
                $submissionReviewer = SubmissionReviewer::firstOrCreate([
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewerId,
                ], [
                    'evaluation_status' => $hasEvaluationForms ? 'pending' : 'not_required',
                ]);

                if ($submissionReviewer->wasRecentlyCreated) {
                    $newlyAssignedReviewers[] = $reviewerId;
                }

                // Auto-assign evaluation forms if enabled and forms exist
                if ($hasEvaluationForms && ($request->auto_assign_forms ?? true)) {
                    $this->autoAssignEvaluationForms($submissionReviewer, $formPhaseDetail);
                }
            }

            // Update submission status
            $submission->updateStatusBasedOnReviews();

            // TAMBAHKAN: Kirim email ke reviewer yang baru di-assign
            foreach ($newlyAssignedReviewers as $reviewerId) {
                $reviewer = Reviewer::find($reviewerId);
                $this->emailService->notifyReviewerAssignment($submission, $reviewer);
            }
        });

        return back()->with('success', 'Berhasil menugaskan reviewer.');
    }

    // Enhanced remove reviewer to handle evaluation forms
    public function removeReviewer(FormSubmission $submission, Reviewer $reviewer)
    {
        DB::transaction(function () use ($submission, $reviewer) {
            $submissionReviewer = SubmissionReviewer::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
            ])->first();

            if (!$submissionReviewer) {
                throw new \Exception('Reviewer tidak ditemukan untuk submission ini.');
            }

            // Check if there are submitted evaluation responses
            $hasSubmittedEvaluations = $submissionReviewer->reviewerFormAssignments()
                ->whereHas('reviewFormResponse', function ($query) {
                    $query->where('status', 'submitted');
                })
                ->exists();

            if ($hasSubmittedEvaluations) {
                throw new \Exception('Tidak dapat menghapus reviewer dengan respons evaluasi yang telah dikirim.');
            }

            // Delete evaluation form assignments and draft responses
            foreach ($submissionReviewer->reviewerFormAssignments as $assignment) {
                /** @var \App\Models\ReviewerFormAssignment $assignment */
                /** @var \App\Models\ReviewFormResponse|null $response */
                $response = $assignment->reviewFormResponse;

                if ($response && $response->isDraft()) {
                    $response->reviewFormFieldResponses()->delete();
                    $response->delete();
                }
                $assignment->delete();
            }

            // Delete submission reviewer record
            $submissionReviewer->delete();

            // Delete all ReviewSummary created by this reviewer
            $reviewSummaries = ReviewSummary::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
            ])->get();

            foreach ($reviewSummaries as $summary) {
                $this->deleteReviewSummaryWithComments($summary);
            }

            // Update submission status
            if ($submission->submissionReviewers()->count() === 0) {
                $submission->update(['status' => SubmissionStatus::PENDING]);
            } else {
                $this->updateSubmissionStatusBasedOnReviews($submission);
            }

            // TAMBAHKAN: Kirim email notifikasi removal
            $this->emailService->notifyReviewerRemoval($submission, $reviewer);
        });

        return back()->with('success', 'Reviewer berhasil dihapus dari submission ini.');
    }

    // Enhanced status update considering evaluation completion
    public function updateSubmissionStatus(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'status' => 'required|in:pending,under_review,needs_revision,approved,rejected',
        ]);

        $user = Auth::user();
        $canUpdate = false;

        // Admin can always update
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            $canUpdate = true;
        }
        // Check reviewer permissions
        else {
            $reviewer = Reviewer::where('user_id', $user->id)->first();

            if ($reviewer) {
                $submissionReviewer = SubmissionReviewer::where([
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewer->id,
                ])->first();

                // Reviewer can update status only if:
                // 1. They are assigned to this submission
                // 2. Evaluations are completed or not required
                if ($submissionReviewer && $submissionReviewer->canParticipateInDiscussions()) {
                    $canUpdate = true;
                } elseif ($submissionReviewer) {
                    $pendingCount = $submissionReviewer->pending_forms_count;
                    abort(403, "Selesaikan {$pendingCount} formulir evaluasi yang tertunda sebelum memperbarui status pengajuan.");
                } else {
                    abort(403, 'Anda tidak ditugaskan sebagai reviewer untuk pengajuan ini.');
                }
            }
        }

        if (!$canUpdate) {
            abort(403, 'Tidak berwenang untuk memperbarui status pengajuan.');
        }

        // Convert string status to enum
        $newStatus = match ($request->status) {
            'pending' => SubmissionStatus::PENDING,
            'under_review' => SubmissionStatus::UNDER_REVIEW,
            'needs_revision' => SubmissionStatus::NEEDS_REVISION,
            'approved' => SubmissionStatus::APPROVED,
            'rejected' => SubmissionStatus::REJECTED,
            default => throw new \InvalidArgumentException('Invalid submission status value'),
        };

        DB::transaction(function () use ($submission, $newStatus) {
            $oldStatus = $submission->status;

            $submission->update(['status' => $newStatus]);

            // TAMBAHKAN: Kirim email notifikasi submission status changed
            $this->emailService->notifySubmissionStatusChanged($submission, $oldStatus);
        });

        return back()->with('success', "Status pengajuan berhasil diubah menjadi {$newStatus->label()}.");
    }

    // Enhanced thread creation with evaluation check
    public function createReviewThread(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'summary_notes' => 'required|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,doc,docx,png,jpg,jpeg,gif|max:10240',
        ]);

        $user = Auth::user();

        // Admin can always create threads
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return $this->performCreateReviewThread($request, $submission, null);
        }

        // Check if user is a reviewer
        $reviewer = Reviewer::where('user_id', $user->id)->first();
        if (!$reviewer) {
            abort(403, 'Anda tidak terdaftar sebagai reviewer');
        }

        // Check if reviewer is assigned to this submission
        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ])->first();

        if (!$submissionReviewer) {
            abort(403, 'Anda tidak ditugaskan sebagai reviewer untuk pengajuan ini.');
        }

        // Get form phase to check if evaluation forms exist
        $formPhaseDetail = $submission->getFormPhaseDetail();

        if (!$formPhaseDetail || !$formPhaseDetail->hasReviewEvaluationForms()) {
            // No evaluation forms, allow thread creation immediately
            return $this->performCreateReviewThread($request, $submission, $reviewer);
        }

        // Has evaluation forms - check if all REQUIRED forms are completed
        $requiredForms = $formPhaseDetail->requiredReviewEvaluationForms()->pluck('id');

        if ($requiredForms->isEmpty()) {
            // No required forms, can create thread
            return $this->performCreateReviewThread($request, $submission, $reviewer);
        }

        // Check completion of required forms
        $completedRequired = $submissionReviewer->reviewerFormAssignments()
            ->whereIn('review_evaluation_form_id', $requiredForms)
            ->whereHas('reviewFormResponse', function ($q) {
                $q->where('status', 'submitted');
            })
            ->count();

        if ($completedRequired < $requiredForms->count()) {
            $pendingCount = $requiredForms->count() - $completedRequired;
            abort(403, "Selesaikan {$pendingCount} formulir evaluasi yang wajib sebelum membuat thread review.");
        }

        // All required forms completed, can create thread
        return $this->performCreateReviewThread($request, $submission, $reviewer);
    }

    protected function performCreateReviewThread(Request $request, FormSubmission $submission, ?Reviewer $reviewer)
    {
        DB::transaction(function () use ($request, $submission, $reviewer) {
            $reviewSummary = ReviewSummary::create([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer?->id,
                'status' => 'open',
                'summary_notes' => $request->summary_notes,
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('review-attachments/' . $submission->id, 'public');

                    ReviewSummaryAttachment::create([
                        'review_summary_id' => $reviewSummary->id,
                        'file_path' => $path,
                    ]);
                }
            }

            // Update submission status if not already under review
            if ($submission->status === SubmissionStatus::PENDING) {
                $submission->update(['status' => SubmissionStatus::UNDER_REVIEW]);
            }

            // TAMBAHKAN: Kirim email notifikasi review thread created
            $this->emailService->notifyReviewThreadCreated($reviewSummary);
        });

        return back()->with('success', 'Thread review berhasil dibuat.');
    }

    // Enhanced comment adding with evaluation check
    public function addComment(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'comment_text' => 'required|string|max:2000',
            'parent_comment_id' => 'nullable|exists:review_comments,id',
        ]);

        $user = Auth::user();
        /** @var \App\Models\FormSubmission $submission */
        $submission = $reviewSummary->formSubmission;
        $reviewerId = null;
        $canComment = false;

        // Admin can always comment
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            $canComment = true;
        }
        // Submitter can always comment on their own submission
        elseif ($submission->submitted_by === $user->id) {
            $canComment = true;
        }
        // Check reviewer permissions
        else {
            $reviewer = Reviewer::where('user_id', $user->id)->first();

            if ($reviewer) {
                $submissionReviewer = SubmissionReviewer::where([
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewer->id,
                ])->first();

                if ($submissionReviewer) {
                    // Check if can participate in discussions
                    if ($submissionReviewer->canParticipateInDiscussions()) {
                        $canComment = true;
                        $reviewerId = $reviewer->id;
                    } else {
                        $pendingCount = $submissionReviewer->pending_forms_count;
                        abort(403, "Selesaikan {$pendingCount} formulir evaluasi yang tertunda sebelum berpartisipasi dalam diskusi.");
                    }
                }
            }
        }

        if (!$canComment) {
            abort(403, 'Anda tidak memiliki izin untuk mengomentari review ini');
        }

        DB::transaction(function () use ($request, $reviewSummary, $user, $reviewerId) {
            $comment = ReviewComment::create([
                'review_summary_id' => $reviewSummary->id,
                'parent_comment_id' => $request->parent_comment_id,
                'user_id' => $reviewerId ? null : $user->id,
                'reviewer_id' => $reviewerId,
                'comment_text' => $request->comment_text,
            ]);

            // TAMBAHKAN: Kirim email notifikasi comment
            $this->emailService->notifyReviewComment($comment);
        });

        return back()->with('success', 'Komentar berhasil ditambahkan.');
    }

    // Get available evaluation forms for assignment
    public function getAvailableEvaluationForms(FormSubmission $submission)
    {
        $formPhaseDetail = $submission->getFormPhaseDetail();

        if (!$formPhaseDetail) {
            return response()->json([]);
        }

        $availableForms = $formPhaseDetail->activeReviewEvaluationForms()
            ->get(['id', 'title', 'description', 'is_required', 'order']);

        return response()->json($availableForms);
    }

    // Get evaluation status for submission
    public function getEvaluationStatus(FormSubmission $submission)
    {
        $evaluationStats = $submission->getEvaluationStats();
        $formsSummary = $submission->getEvaluationFormsSummary();

        return response()->json([
            'evaluation_stats' => $evaluationStats,
            'forms_summary' => $formsSummary,
            'discussions_allowed' => $submission->discussionsAllowed(),
            'can_proceed_to_discussions' => $submission->canProceedToDiscussions(),
        ]);
    }

    // Assign specific evaluation forms to reviewer
    public function assignEvaluationFormsToReviewer(Request $request, FormSubmission $submission, Reviewer $reviewer)
    {
        $request->validate([
            'form_assignments' => 'required|array|min:1',
            'form_assignments.*.form_id' => 'required|exists:review_evaluation_forms,id',
            'form_assignments.*.is_required' => 'boolean',
            'form_assignments.*.due_date' => 'nullable|date|after:now',
        ]);

        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ])->first();

        if (!$submissionReviewer) {
            return back()->withErrors(['error' => 'Reviewer tidak ditugaskan untuk pengajuan ini.']);
        }

        try {
            DB::beginTransaction();

            foreach ($request->form_assignments as $assignment) {
                $submissionReviewer->assignForm(
                    $assignment['form_id'],
                    $assignment['is_required'] ?? true,
                    $assignment['due_date'] ? new \DateTime($assignment['due_date']) : null
                );
            }

            DB::commit();

            return back()->with('success', 'Formulir evaluasi berhasil ditugaskan.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menugaskan formulir evaluasi: ' . $e->getMessage()]);
        }
    }

    // Enhanced available reviewers with evaluation context
    public function getAvailableReviewers(FormSubmission $submission)
    {
        $assignedReviewerIds = SubmissionReviewer::where('form_submission_id', $submission->id)
            ->pluck('reviewer_id')
            ->toArray();

        $availableReviewers = Reviewer::with(['user', 'reviewerRole'])
            ->whereHas('user', function ($query) use ($submission) {
                $query->where('id', '!=', $submission->submitted_by);
            })
            ->whereNotIn('id', $assignedReviewerIds)
            ->get()
            ->map(function ($reviewer) {
                /** @var \App\Models\Reviewer $reviewer */
                return [
                    'id' => $reviewer->id,
                    'name' => $reviewer->user->name,
                    'email' => $reviewer->user->email,
                    'role' => $reviewer->reviewerRole->name,
                ];
            });

        // Get available evaluation forms
        $evaluationForms = $this->getSubmissionFormPhase($submission)
                ?->activeReviewEvaluationForms()
            ->get(['id', 'title', 'is_required', 'order']) ?? collect();

        return response()->json([
            'reviewers' => $availableReviewers,
            'evaluation_forms' => $evaluationForms,
            'has_evaluation_forms' => $evaluationForms->isNotEmpty(),
        ]);
    }

    // Auto-assign evaluation forms to new reviewers
    protected function autoAssignEvaluationForms(SubmissionReviewer $submissionReviewer, FormPhaseDetail $formPhaseDetail): void
    {
        // Get all required evaluation forms
        $requiredForms = $formPhaseDetail->requiredReviewEvaluationForms()->get();

        /** @var \App\Models\FormSubmission $submission */
        $submission = $submissionReviewer->formSubmission;

        $dueDate = $this->getEvaluationDueDate($submissionReviewer->formSubmission);

        foreach ($requiredForms as $form) {
            /** @var \App\Models\ReviewEvaluationForm $form */
            $exists = $submissionReviewer->reviewerFormAssignments()
                ->where('review_evaluation_form_id', $form->id)
                ->exists();

            if (!$exists) {
                $submissionReviewer->assignForm(
                    $form->id,
                    true, // is_required
                    $dueDate
                );
            }
        }
    }

    protected function getSubmissionFormPhase(FormSubmission $submission)
    {
        return FormPhase::whereHas('formPhaseDetails.formAccessControl', function ($query) use ($submission) {
            $query->where('form_id', $submission->form_id);
        })->first();
    }

    protected function getEvaluationDueDate(FormSubmission $submission): ?\DateTime
    {
        $submissionPeriod = SubmissionPeriod::whereHas(
            'submissionPeriodPhases.formPhase.formPhaseDetails.formAccessControl',
            function ($query) use ($submission) {
                $query->where('form_id', $submission->form_id);
            }
        )->first();

        if (!$submissionPeriod) {
            return new \DateTime('+7 days');
        }

        /** @var \App\Models\SubmissionDate|null $latestDate */
        $latestDate = $submissionPeriod->submissionDates()
            ->orderBy('datetime', 'desc')
            ->first();

        return $latestDate
            ? new \DateTime($latestDate->datetime)
            : new \DateTime('+7 days');
    }

    // Existing methods with minor updates...
    public function updateReviewStatus(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'status' => 'required|in:open,resolved,closed',
        ]);

        $user = Auth::user();
        $canUpdate = false;

        // Admin can always update
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            $canUpdate = true;
        }
        // Check if it's the reviewer's own review
        elseif ($reviewSummary->reviewer_id) {
            $reviewer = Reviewer::where('user_id', $user->id)->first();

            if ($reviewer && $reviewSummary->reviewer_id === $reviewer->id) {
                // Check if reviewer has completed evaluations (if required)
                $submissionReviewer = SubmissionReviewer::where([
                    'form_submission_id' => $reviewSummary->form_submission_id,
                    'reviewer_id' => $reviewer->id,
                ])->first();

                if ($submissionReviewer) {
                    // Check if evaluations are complete
                    if ($submissionReviewer->canParticipateInDiscussions()) {
                        $canUpdate = true;
                    } else {
                        $pendingCount = $submissionReviewer->pending_forms_count;
                        abort(403, "Selesaikan {$pendingCount} formulir evaluasi yang tertunda sebelum memperbarui status review.");
                    }
                }
            }
        }

        if (!$canUpdate) {
            abort(403, 'Anda tidak memiliki izin untuk memperbarui status thread review');
        }

        DB::transaction(function () use ($request, $reviewSummary) {
            $oldStatus = $reviewSummary->status;

            $reviewSummary->update([
                'status' => $request->status,
            ]);

            /** @var \App\Models\FormSubmission $submission */
            $submission = $reviewSummary->formSubmission;

            // Auto-update submission status based on review statuses
            $this->updateSubmissionStatusBasedOnReviews($reviewSummary->formSubmission);

            // TAMBAHKAN: Kirim email notifikasi status changed
            $this->emailService->notifyReviewStatusChanged($reviewSummary, $oldStatus);
        });

        $statusText = match ($request->status) {
            'resolved' => 'diselesaikan',
            'closed' => 'ditutup',
            'open' => 'dibuka kembali',
            default => throw new \InvalidArgumentException('Invalid review status value'),
        };

        return back()->with('success', "Thread review berhasil {$statusText}.");
    }

    public function downloadAttachment(Request $request)
    {
        $filePath = $request->query('path');

        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            abort(404, 'File tidak ditemukan');
        }

        $fullPath = Storage::disk('public')->path($filePath);

        return response()->download($fullPath);
    }

    // Enhanced status update method considering evaluations
    private function updateSubmissionStatusBasedOnReviews(FormSubmission $submission)
    {
        $reviewSummaries = ReviewSummary::where('form_submission_id', $submission->id)->get();

        // Check evaluation completion first
        if ($submission->hasPendingEvaluations()) {
            $submission->update(['status' => SubmissionStatus::UNDER_REVIEW]);

            return;
        }

        // If no review threads exist
        if ($reviewSummaries->isEmpty()) {
            return; // Keep current status
        }

        // Priority order: closed > open > resolved
        if ($reviewSummaries->where('status', 'closed')->isNotEmpty()) {
            $submission->update(['status' => SubmissionStatus::REJECTED]);
        } elseif ($reviewSummaries->where('status', 'open')->isNotEmpty()) {
            $submission->update(['status' => SubmissionStatus::NEEDS_REVISION]);
        } elseif ($reviewSummaries->every(fn($r) => $r->status === 'resolved')) {
            $submission->update(['status' => SubmissionStatus::APPROVED]);
        } else {
            $submission->update(['status' => SubmissionStatus::UNDER_REVIEW]);
        }
    }

    /** @phpstan-ignore-next-line */
    private function checkIfEvaluationsArePositive(FormSubmission $submission): bool
    {
        // This is a simplified logic - you can customize based on your evaluation criteria
        // For example, check if there are any "rejection" responses in evaluation forms

        $submittedResponses = $submission->submittedReviewFormResponses()->get();

        // If no evaluations submitted, default to needs review
        if ($submittedResponses->isEmpty()) {
            return false;
        }

        // Custom logic: check for specific field values that indicate rejection
        foreach ($submittedResponses as $response) {
            $fieldResponses = $response->reviewFormFieldResponses()->get();

            foreach ($fieldResponses as $fieldResponse) {
                $field = $fieldResponse->reviewFormField;

                // Example: if there's a field with "recommendation" and value is "reject"
                if (
                    str_contains(strtolower($field->label), 'recommendation') ||
                    str_contains(strtolower($field->label), 'decision')
                ) {

                    $value = strtolower($fieldResponse->value);
                    if (
                        str_contains($value, 'reject') ||
                        str_contains($value, 'decline') ||
                        str_contains($value, 'not approved')
                    ) {
                        return false;
                    }
                }
            }
        }

        return true; // Default to positive if no negative indicators found
    }

    private function deleteReviewSummaryWithComments(ReviewSummary $summary)
    {
        $comments = ReviewComment::where('review_summary_id', $summary->id)->get();
        foreach ($comments as $comment) {
            /** @phpstan-ignore-next-line */
            foreach ($comment->attachments as $attachment) {
                /** @var \App\Models\ReviewCommentAttachment $attachment */
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }
            $comment->delete();
        }

        foreach ($summary->attachments as $attachment) {
            /** @var \App\Models\ReviewSummaryAttachment $attachment */
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
        }

        $summary->delete();
    }

    /** @phpstan-ignore-next-line */
    private function canUserReview(FormSubmission $submission, $user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        $reviewer = Reviewer::where('user_id', $user->id)->latest()->first();
        if (!$reviewer) {
            return false;
        }

        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ])->first();

        if (!$submissionReviewer) {
            return false;
        }

        // If no evaluation forms required, can review immediately
        if (!$submission->hasReviewEvaluationForms()) {
            return true;
        }

        // If has evaluation forms, must complete them first to fully participate
        // But can view the submission
        return true;
    }
}
