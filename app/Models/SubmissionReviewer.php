<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $reviewer_id
 * @property int $form_submission_id
 * @property string $status
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
        'status',
    ];

    protected $casts = [
        'evaluation_status' => 'string',
        'status' => 'string',
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

    /** Penugasan yang masih berlaku; yang `replaced` adalah sejarah. */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * BR-REV-05 — satu definisi "belum selesai", dipakai `canProceed()` dan
     * `PendingEvaluationCheck`. Bukan `= 'pending'`: status evaluasi yang
     * kelak ditambahkan otomatis terhitung belum selesai, bukan diam-diam beres.
     *
     * @param  Builder<SubmissionReviewer>  $query
     * @return Builder<SubmissionReviewer>
     */
    public function scopeUnsettledEvaluation(Builder $query): Builder
    {
        return $query->active()->whereNotIn('evaluation_status', self::SETTLED_EVALUATION);
    }

    /**
     * Status evaluasi yang dihitung selesai untuk BR-REV-05, BR-REV-06,
     * dan BR-FE-06.
     *
     * `not_required` ikut, dan itu keharusan bukan kelonggaran: dibaca
     * harfiah sebagai `completed` saja, reviewer yang memang tidak
     * diwajibkan menilai tidak akan pernah selesai dan submission-nya
     * terkunci selamanya tanpa ada yang salah (register #14).
     */
    public const SETTLED_EVALUATION = ['completed', 'not_required'];

    /** Apakah evaluasi penugasan ini sudah tidak ditunggu lagi. */
    public function hasSettledEvaluation(): bool
    {
        return in_array($this->evaluation_status, self::SETTLED_EVALUATION, true);
    }

    public function markAsReplaced(): void
    {
        $this->update(['status' => 'replaced']);
    }

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

    // NEW: Get pending forms count
    public function getPendingFormsCountAttribute(): int
    {
        return $this->pendingReviewerFormAssignments()->count();
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
}
