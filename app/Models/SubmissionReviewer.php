<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $reviewer_id
 * @property int $form_submission_id
 * @property-read FormSubmission $formSubmission
 * @property-read Reviewer $reviewer
 *
 * @method bool hasAllRequiredFormsCompleted()
 */
class SubmissionReviewer extends Model
{
    protected $fillable = [
        'form_submission_id',
        'reviewer_id',
        'evaluation_status', // NEW
    ];

    protected $casts = [
        'evaluation_status' => 'string',
    ];

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /**
     * @return BelongsTo<Reviewer, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Reviewer::class);
    }

    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function reviewerFormAssignments(): HasMany
    {
        return $this->hasMany(ReviewerFormAssignment::class);
    }

    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function activeReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()->active();
    }

    // NEW: Required form assignments
    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function requiredReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()->required()->active();
    }

    // NEW: Optional form assignments
    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function optionalReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()->optional()->active();
    }

    // NEW: Completed form assignments
    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function completedReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()
            ->active()
            ->whereHas('reviewFormResponse', function ($query) {
                $query->where('status', 'submitted');
            });
    }

    // NEW: Pending form assignments
    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function pendingReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()
            ->active()
            ->whereDoesntHave('reviewFormResponse', function ($query) {
                $query->where('status', 'submitted');
            });
    }

    // NEW: Overdue form assignments
    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function overdueReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()->overdue();
    }

    // NEW: Form assignments due soon
    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function dueSoonReviewerFormAssignments(int $hours = 24): HasMany
    {
        return $this->reviewerFormAssignments()->dueSoon($hours);
    }

    // NEW: Check if reviewer has any assigned forms
    public function hasAssignedForms(): bool
    {
        return $this->activeReviewerFormAssignments()->exists();
    }

    // NEW: Check if all required forms are completed
    public function hasAllRequiredFormsCompleted(): bool
    {
        $requiredCount = $this->requiredReviewerFormAssignments()->count();

        if ($requiredCount === 0) {
            return true; // No required forms means completed
        }

        $completedCount = $this->requiredReviewerFormAssignments()
            ->whereHas('reviewFormResponse', function ($query) {
                $query->where('status', 'submitted');
            })
            ->count();

        return $completedCount === $requiredCount;
    }

    // NEW: Get evaluation completion percentage
    public function getEvaluationCompletionPercentage(): int
    {
        $totalAssignments = $this->activeReviewerFormAssignments()->count();

        if ($totalAssignments === 0) {
            return 100;
        }

        $completedAssignments = $this->completedReviewerFormAssignments()->count();

        return round(($completedAssignments / $totalAssignments) * 100);
    }

    // NEW: Update evaluation status based on current form completion
    public function updateEvaluationStatus(): void
    {
        if (!$this->hasAssignedForms()) {
            $this->update(['evaluation_status' => 'not_required']);

            return;
        }

        if ($this->hasAllRequiredFormsCompleted()) {
            $this->update(['evaluation_status' => 'completed']);
        } else {
            $this->update(['evaluation_status' => 'pending']);
        }
    }

    // NEW: Check if reviewer can create discussion threads
    public function canCreateDiscussionThreads(): bool
    {
        return $this->evaluation_status === 'completed' ||
            $this->evaluation_status === 'not_required';
    }

    // NEW: Check if reviewer can participate in discussions
    public function canParticipateInDiscussions(): bool
    {
        return $this->canCreateDiscussionThreads();
    }

    // NEW: Get status label for display
    public function getEvaluationStatusLabelAttribute(): string
    {
        return match ($this->evaluation_status) {
            'pending' => 'Evaluation Pending',
            'completed' => 'Evaluation Completed',
            'not_required' => 'No Evaluation Required',
            default => 'Unknown Status'
        };
    }

    // NEW: Get status color for UI
    public function getEvaluationStatusColorAttribute(): string
    {
        return match ($this->evaluation_status) {
            'pending' => 'orange',
            'completed' => 'green',
            'not_required' => 'blue',
            default => 'gray'
        };
    }

    // NEW: Get pending forms count
    public function getPendingFormsCountAttribute(): int
    {
        return $this->pendingReviewerFormAssignments()->count();
    }

    // NEW: Get completed forms count
    public function getCompletedFormsCountAttribute(): int
    {
        return $this->completedReviewerFormAssignments()->count();
    }

    // NEW: Get overdue forms count
    public function getOverdueFormsCountAttribute(): int
    {
        return $this->overdueReviewerFormAssignments()->count();
    }

    // NEW: Auto-lock overdue forms
    public function autoLockOverdueForms(): void
    {
        $overdueAssignments = $this->overdueReviewerFormAssignments()->get();

        foreach ($overdueAssignments as $assignment) {
            $assignment->autoLockIfOverdue();
        }

        // Update evaluation status after locking
        $this->updateEvaluationStatus();
    }

    // NEW: Create form assignment
    public function assignForm(int $reviewEvaluationFormId, bool $isRequired = true, ?\DateTime $dueDate = null): ReviewerFormAssignment
    {
        /** @var ReviewerFormAssignment $assignment */
        $assignment = $this->reviewerFormAssignments()->create([
            'review_evaluation_form_id' => $reviewEvaluationFormId,
            'is_required' => $isRequired,
            'due_date' => $dueDate,
            'assigned_at' => now(),
        ]);

        // Update evaluation status
        $this->updateEvaluationStatus();

        return $assignment;
    }

    // NEW: Remove form assignment
    public function removeFormAssignment(int $reviewEvaluationFormId): bool
    {
        $deleted = $this->reviewerFormAssignments()
            ->where('review_evaluation_form_id', $reviewEvaluationFormId)
            ->delete();

        if ($deleted > 0) {
            $this->updateEvaluationStatus();
        }

        return $deleted > 0;
    }
}
