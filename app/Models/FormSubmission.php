<?php

namespace App\Models;

use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property int $form_id
 * @property int $submitted_by
 * @property Carbon|null $submitted_at
 * @property-read Form $form
 * @property-read User $submittedBy
 * @property-read Collection<int, FormFieldResponse> $formFieldResponses
 * @property-read Collection<int, SubmissionReviewer> $submissionReviewers
 * @property-read Collection<int, ReviewSummary> $reviewSummaries
 * @property-read Collection<int, BudgetLineItem> $budgetLineItems
 * @property SubmissionStatus|null $status
 */
class FormSubmission extends Model
{
    use HasFactory, HasStates;

    protected $fillable = [
        'form_id',
        'is_submitted',
        'status',
        'submitted_by',
    ];

    protected $casts = [
        'status' => SubmissionStatus::class,
        'is_submitted' => 'boolean',
    ];

     /** @return BelongsTo<FormSubmission, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'parent_submission_id');
    }

    /** @return HasMany<FormSubmission, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'parent_submission_id');
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<FormFieldResponse, $this>
     */
    public function formFieldResponses(): HasMany
    {
        return $this->hasMany(FormFieldResponse::class);
    }

    public function scheme(): ?Scheme
    {
        $response = $this->formFieldResponses()
            ->whereHas('formField', function ($query) {
                $query->whereHas('fieldType', function ($query) {
                    $query->where('name', 'scheme_selector');
                });
            })
            ->first();

        return $response ? Scheme::find($response->value) : null;
    }

    /** @return HasMany<SubmissionReviewer, $this> */
    public function submissionReviewers(): HasMany
    {
        return $this->hasMany(SubmissionReviewer::class);
    }

    /** @return HasMany<ReviewSummary, $this> */
    public function reviewSummaries(): HasMany
    {
        return $this->hasMany(ReviewSummary::class);
    }

    /** @return HasMany<BudgetLineItem, $this> */
    public function budgetLineItems(): HasMany
    {
        return $this->hasMany(BudgetLineItem::class);
    }

    public function reviewComments()
    {
        return $this->hasMany(ReviewComment::class, 'review_summary_id', 'id')
            ->join('review_summaries', 'review_comments.review_summary_id', '=', 'review_summaries.id')
            ->where('review_summaries.form_submission_id', $this->id);
    }

    public function allReviewComments()
    {
        return $this->hasManyThrough(
            ReviewComment::class,
            ReviewSummary::class,
            'form_submission_id',
            'review_summary_id',
            'id',
            'id'
        );
    }

    // NEW: Get all reviewer form assignments through submission reviewers
    public function reviewerFormAssignments()
    {
        return $this->hasManyThrough(
            ReviewerFormAssignment::class,
            SubmissionReviewer::class,
            'form_submission_id',
            'submission_reviewer_id',
            'id',
            'id'
        );
    }

    // NEW: Get all review form responses
    public function reviewFormResponses()
    {
        return $this->hasManyThrough(
            ReviewFormResponse::class,
            ReviewerFormAssignment::class,
            'submission_reviewer_id',
            'reviewer_form_assignment_id',
            'id',
            'id'
        )->whereIn(
            'reviewer_form_assignments.submission_reviewer_id',
            $this->submissionReviewers()->pluck('id')
        );
    }

    // NEW: Get submitted review form responses
    public function submittedReviewFormResponses()
    {
        return $this->reviewFormResponses()->where('review_form_responses.status', 'submitted');
    }

    // NEW: Check if all reviewers have completed their evaluations
    public function allReviewersCompletedEvaluations(): bool
    {
        $reviewersWithPendingEvaluations = $this->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->count();

        return $reviewersWithPendingEvaluations === 0;
    }

    // NEW: Check if any reviewer has pending evaluations
    public function hasPendingEvaluations(): bool
    {
        return $this->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->exists();
    }

    // NEW: Get reviewers with completed evaluations
    public function reviewersWithCompletedEvaluations()
    {
        return $this->submissionReviewers()
            ->where('evaluation_status', 'completed')
            ->with(['reviewer.user', 'reviewer.reviewerRole']);
    }

    // NEW: Get reviewers with pending evaluations
    public function reviewersWithPendingEvaluations()
    {
        return $this->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->with(['reviewer.user', 'reviewer.reviewerRole']);
    }

    // NEW: Get evaluation completion statistics
    public function getEvaluationStats(): array
    {
        $totalReviewers = $this->submissionReviewers()->count();
        $completedEvaluations = $this->submissionReviewers()
            ->where('evaluation_status', 'completed')
            ->count();
        $pendingEvaluations = $this->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->count();
        $noEvaluationRequired = $this->submissionReviewers()
            ->where('evaluation_status', 'not_required')
            ->count();

        $completionPercentage = $totalReviewers > 0
            ? round((($completedEvaluations + $noEvaluationRequired) / $totalReviewers) * 100)
            : 100;

        return [
            'total_reviewers' => $totalReviewers,
            'completed_evaluations' => $completedEvaluations,
            'pending_evaluations' => $pendingEvaluations,
            'no_evaluation_required' => $noEvaluationRequired,
            'completion_percentage' => $completionPercentage,
            'all_completed' => $pendingEvaluations === 0,
        ];
    }

    // NEW: Auto-assign evaluation forms to new reviewers
    public function autoAssignEvaluationForms(): void
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        if (!$formPhaseDetail || !$formPhaseDetail->hasReviewEvaluationForms()) {
            return;
        }

        // Get reviewers without any form assignments
        $reviewersNeedingAssignment = $this->submissionReviewers()
            ->whereDoesntHave('reviewerFormAssignments')
            ->get();

        foreach ($reviewersNeedingAssignment as $submissionReviewer) {
            /** @var SubmissionReviewer $submissionReviewer */
            // Assign all required forms by default
            /** @var Collection<int, ReviewEvaluationForm> $requiredForms */
            $requiredForms = $formPhaseDetail->requiredReviewEvaluationForms()->get();

            foreach ($requiredForms as $form) {
                $submissionReviewer->assignForm($form->id, true, $this->getEvaluationDueDate());
            }
        }
    }

    public function getFormPhase(): ?FormPhase
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        return $formPhaseDetail ? $formPhaseDetail->formPhase : null;
    }

    // NEW: Get form phase for this submission
    public function getFormPhaseDetail(): ?FormPhaseDetail
    {
        return FormPhaseDetail::whereHas('formAccessControl', function ($query) {
            $query->where('form_id', $this->form_id);
        })->first();
    }

    // NEW: Get evaluation due date based on submission period
    protected function getEvaluationDueDate(): ?\DateTime
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        if ($formPhaseDetail === null || $formPhaseDetail->formPhase === null) {
            return null;
        }

        // Get submission period for this form phase
        $submissionPeriod = SubmissionPeriod::whereHas('submissionPeriodPhases', function ($query) use ($formPhaseDetail) {
            $query->where('form_phase_id', $formPhaseDetail->form_phase_id);
        })->first();

        if (!$submissionPeriod) {
            return null;
        }

        // Get the latest submission date as due date
        /** @var SubmissionDate|null $latestDate */
        $latestDate = $submissionPeriod->submissionDates()
            ->orderBy('date', 'desc')
            ->first();

        return $latestDate ? $latestDate->date : null;
    }

    // NEW: Check if evaluation phase is complete and can proceed to discussions
    public function canProceedToDiscussions(): bool
    {
        return $this->allReviewersCompletedEvaluations();
    }

    // Updated: Enhanced canProceed method
    public function canProceed(): bool
    {
        return $this->status instanceof Approved
            && $this->allReviewersApproved()
            && $this->allReviewersCompletedEvaluations();
    }

    public function needsRevision(): bool
    {
        return $this->status instanceof NeedsRevision;
    }

    // Updated: Check if discussions are allowed
    public function discussionsAllowed(): bool
    {
        // Discussions are allowed if:
        // 1. No evaluation forms required, OR
        // 2. All required evaluations are completed
        return !$this->hasPendingEvaluations();
    }

    // Existing helper methods
    public function allReviewersApproved()
    {
        $totalReviewers = $this->submissionReviewers()->count();
        $approvedReviewers = $this->reviewSummaries()
            ->where('status', 'resolved')
            ->distinct('reviewer_id')
            ->count('reviewer_id');

        return $totalReviewers > 0 && $totalReviewers === $approvedReviewers;
    }

    public function hasRejectedReviews()
    {
        return $this->reviewSummaries()
            ->where('status', 'closed')
            ->exists();
    }

    public function hasRevisionsRequested()
    {
        return $this->reviewSummaries()
            ->where('status', 'open')
            ->exists();
    }

    public function hasAnyReviews()
    {
        return $this->reviewSummaries()->exists();
    }

    // Updated: Enhanced status update considering evaluations
    public function updateStatusBasedOnReviews()
    {
        $targetState = null;

        if ($this->hasPendingEvaluations()) {
            $targetState = UnderReview::class;
        } elseif ($this->hasRejectedReviews()) {
            $targetState = Rejected::class;
        } elseif ($this->hasRevisionsRequested()) {
            $targetState = NeedsRevision::class;
        } elseif ($this->allReviewersApproved()) {
            $targetState = Approved::class;
        } elseif ($this->hasAnyReviews()) {
            $targetState = UnderReview::class;
        }

        if (!$targetState) {
            return;
        }

        if ($this->status->canTransitionTo($targetState)) {
            $this->status->transitionTo($targetState);
        } elseif ($this->status->canTransitionTo(UnderReview::class)) {
            $this->status->transitionTo(UnderReview::class);

            /** @phpstan-ignore-next-line */
            if ($this->status->canTransitionTo($targetState)) {
                $this->status->transitionTo($targetState);
            }
        }
    }

    public function getActiveReviewThreadsCount(): int
    {
        return $this->reviewSummaries()->where('status', 'open')->count();
    }

    public function getResolvedReviewThreadsCount(): int
    {
        return $this->reviewSummaries()->where('status', 'resolved')->count();
    }

    public function hasOpenReviewThreads(): bool
    {
        return $this->reviewSummaries()->where('status', 'open')->exists();
    }

    public function assignReviewer(int $reviewerId): ReviewSummary
    {
        /** @var ReviewSummary $summary */
        $summary = $this->reviewSummaries()->firstOrCreate(
            ['reviewer_id' => $reviewerId],
            ['status' => 'open']
        );

        return $summary;
    }

    public function removeReviewer(int $reviewerId): bool
    {
        return $this->reviewSummaries()
            ->where('reviewer_id', $reviewerId)
            ->delete() > 0;
    }

    // NEW: Get evaluation forms summary for this submission
    public function getEvaluationFormsSummary(): array
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        if (!$formPhaseDetail) {
            return [];
        }

        /** @var Collection<int, ReviewEvaluationForm> $evaluationForms */
        $evaluationForms = $formPhaseDetail->activeReviewEvaluationForms()->get();
        $summary = [];

        foreach ($evaluationForms as $form) {
            $assignedCount = $this->reviewerFormAssignments()
                ->where('review_evaluation_form_id', $form->id)
                ->count();

            $completedCount = $this->reviewerFormAssignments()
                ->where('review_evaluation_form_id', $form->id)
                ->whereHas('reviewFormResponse', function ($query) {
                    $query->where('status', 'submitted');
                })
                ->count();

            $summary[] = [
                'form' => $form,
                'assigned_count' => $assignedCount,
                'completed_count' => $completedCount,
                'completion_percentage' => $assignedCount > 0
                    ? round(($completedCount / $assignedCount) * 100)
                    : 0,
            ];
        }

        return $summary;
    }

    // Check if form phase has any review evaluation forms
    public function hasReviewEvaluationForms(): bool
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        return $formPhaseDetail ? $formPhaseDetail->hasReviewEvaluationForms() : false;
    }

    // Check if review evaluation forms are required for this submission
    public function requiresReviewEvaluation(): bool
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        return $formPhaseDetail ? $formPhaseDetail->requiresEvaluationCompletion() : false;
    }

    // Check if a specific reviewer has completed their evaluation (or if evaluation not required)
    public function reviewerCanCreateThreads(int $reviewerId): bool
    {
        // If no evaluation forms required, reviewer can create threads immediately
        if (!$this->hasReviewEvaluationForms()) {
            return true;
        }

        // Check if reviewer is assigned
        $submissionReviewer = $this->submissionReviewers()
            ->where('reviewer_id', $reviewerId)
            ->first();

        if ($submissionReviewer === null) {
            return false;
        }

        /** @var SubmissionReviewer $submissionReviewer */
        return $submissionReviewer->canCreateDiscussionThreads();
    }

    // Check if a specific reviewer can participate in discussions
    public function reviewerCanParticipate(int $reviewerId): bool
    {
        // Same logic as canCreateThreads
        return $this->reviewerCanCreateThreads($reviewerId);
    }

    // Get evaluation requirements summary
    public function getEvaluationRequirements(): array
    {
        if (!$this->hasReviewEvaluationForms()) {
            return [
                'required' => false,
                'has_forms' => false,
                'message' => 'No evaluation forms required for this submission.',
            ];
        }

        $formPhaseDetail = $this->getFormPhaseDetail();
        if (!$formPhaseDetail) {
            return [
                'required' => false,
                'has_forms' => false,
                'message' => 'No form phase detail found.',
            ];
        }

        $formsCount = $formPhaseDetail->review_evaluation_forms_count;
        $requiredCount = $formPhaseDetail->required_review_evaluation_forms_count;

        return [
            'required' => true,
            'has_forms' => true,
            'total_forms' => $formsCount,
            'required_forms' => $requiredCount,
            'message' => 'This submission requires evaluation forms to be completed before creating review threads.',
        ];
    }
}
