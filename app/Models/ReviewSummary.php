<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $form_submission_id
 * @property int|null $reviewer_id
 * @property string $status
 * @property-read FormSubmission|null $formSubmission
 * @property-read Reviewer|null $reviewer
 * @property-read Collection<int, ReviewSummaryAttachment> $attachments
 */
class ReviewSummary extends Model
{
    protected $fillable = [
        'form_submission_id',
        'reviewer_id',
        'status',
        'summary_notes',
    ];

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /** @return BelongsTo<Reviewer, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Reviewer::class);
    }

    /** @return HasMany<ReviewSummaryAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReviewSummaryAttachment::class);
    }

    public function reviewComments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }

    // NEW: Get related review form responses for this reviewer and submission
    public function relatedReviewFormResponses()
    {
        if (!$this->reviewer_id) {
            return collect();
        }

        return ReviewFormResponse::whereHas('reviewerFormAssignment.submissionReviewer', function ($query) {
            $query->where('form_submission_id', $this->form_submission_id)
                ->where('reviewer_id', $this->reviewer_id);
        })->where('status', 'submitted')->get();
    }

    // NEW: Create review summary from evaluation form responses
    public static function createFromEvaluationResponses(
        int $formSubmissionId,
        int $reviewerId,
        string $status = 'open'
    ): ?self {
        // Check if summary already exists
        $existing = self::where([
            'form_submission_id' => $formSubmissionId,
            'reviewer_id' => $reviewerId,
        ])->first();

        if ($existing) {
            return $existing;
        }

        // Get submission reviewer
        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $formSubmissionId,
            'reviewer_id' => $reviewerId,
        ])->first();

        if (!$submissionReviewer) {
            return null;
        }

        // Get completed evaluation responses
        $completedResponses = $submissionReviewer->reviewerFormAssignments()
            ->with(['reviewFormResponse', 'reviewEvaluationForm'])
            ->whereHas('reviewFormResponse', function ($query) {
                $query->where('status', 'submitted');
            })
            ->get();

        if ($completedResponses->isEmpty()) {
            return null;
        }

        // Generate summary notes from all completed evaluations
        $summaryNotes = self::generateSummaryFromResponses($completedResponses);

        // Create the review summary
        return self::create([
            'form_submission_id' => $formSubmissionId,
            'reviewer_id' => $reviewerId,
            'status' => $status,
            'summary_notes' => $summaryNotes,
        ]);
    }

    // NEW: Generate summary notes from evaluation responses
    protected static function generateSummaryFromResponses($completedResponses): string
    {
        $summary = "Evaluation Summary\n".str_repeat('=', 50)."\n\n";

        foreach ($completedResponses as $assignment) {
            $response = $assignment->reviewFormResponse;
            $form = $assignment->reviewEvaluationForm;

            $summary .= "Form: {$form->title}\n";
            $summary .= "Completed: {$response->submitted_at->format('d M Y H:i')}\n";
            $summary .= str_repeat('-', 30)."\n";

            // Add field responses
            $fieldResponses = $response->reviewFormFieldResponses()->with('reviewFormField')->get();

            foreach ($fieldResponses as $fieldResponse) {
                if ($fieldResponse->hasValue()) {
                    $summary .= "{$fieldResponse->reviewFormField->label}: {$fieldResponse->formatted_value}\n";
                }
            }

            // Add final notes if any
            if ($response->final_notes) {
                $summary .= "\nAdditional Notes:\n{$response->final_notes}\n";
            }

            $summary .= "\n";
        }

        return trim($summary);
    }

    // NEW: Update summary from latest evaluation responses
    public function updateFromEvaluationResponses(): bool
    {
        if (!$this->reviewer_id) {
            return false;
        }

        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $this->form_submission_id,
            'reviewer_id' => $this->reviewer_id,
        ])->first();

        if (!$submissionReviewer) {
            return false;
        }

        $completedResponses = $submissionReviewer->reviewerFormAssignments()
            ->with(['reviewFormResponse', 'reviewEvaluationForm'])
            ->whereHas('reviewFormResponse', function ($query) {
                $query->where('status', 'submitted');
            })
            ->get();

        if ($completedResponses->isEmpty()) {
            return false;
        }

        $newSummaryNotes = self::generateSummaryFromResponses($completedResponses);

        return $this->update(['summary_notes' => $newSummaryNotes]);
    }

    // NEW: Get evaluation completion status for this reviewer
    public function getEvaluationCompletionStatus(): array
    {
        if (!$this->reviewer_id) {
            return [
                'has_evaluations' => false,
                'total_assigned' => 0,
                'completed' => 0,
                'pending' => 0,
                'completion_percentage' => 100,
            ];
        }

        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $this->form_submission_id,
            'reviewer_id' => $this->reviewer_id,
        ])->first();

        if (!$submissionReviewer) {
            return [
                'has_evaluations' => false,
                'total_assigned' => 0,
                'completed' => 0,
                'pending' => 0,
                'completion_percentage' => 100,
            ];
        }

        $totalAssigned = $submissionReviewer->activeReviewerFormAssignments()->count();
        $completed = $submissionReviewer->completedReviewerFormAssignments()->count();
        $pending = $submissionReviewer->pendingReviewerFormAssignments()->count();

        return [
            'has_evaluations' => $totalAssigned > 0,
            'total_assigned' => $totalAssigned,
            'completed' => $completed,
            'pending' => $pending,
            'completion_percentage' => $totalAssigned > 0 ? round(($completed / $totalAssigned) * 100) : 100,
        ];
    }

    // NEW: Get status label with evaluation context
    public function getStatusWithEvaluationContextAttribute(): string
    {
        $evaluationStatus = $this->getEvaluationCompletionStatus();

        if (!$evaluationStatus['has_evaluations']) {
            return match ($this->status) {
                'open' => 'Discussion Open',
                'resolved' => 'Discussion Resolved',
                'closed' => 'Discussion Closed',
                default => ucfirst($this->status)
            };
        }

        $baseStatus = match ($this->status) {
            'open' => 'Open',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => ucfirst($this->status)
        };

        if ($evaluationStatus['pending'] > 0) {
            return "{$baseStatus} (Evaluation: {$evaluationStatus['completed']}/{$evaluationStatus['total_assigned']})";
        }

        return "{$baseStatus} (Evaluation Complete)";
    }

    // NEW: Check if discussions should be allowed for this review
    public function discussionsAllowed(): bool
    {
        if (!$this->reviewer_id) {
            return true; // General discussions always allowed
        }

        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $this->form_submission_id,
            'reviewer_id' => $this->reviewer_id,
        ])->first();

        return $submissionReviewer ? $submissionReviewer->canParticipateInDiscussions() : true;
    }

    // Existing scopes and methods...
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }
}
