<?php

namespace App\Models;

use App\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $fillable = [
        'form_id',
        'is_submitted',
        'status',
        'submitted_by'
    ];

    protected $casts = [
        'status' => SubmissionStatus::class,
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function formFieldResponses()
    {
        return $this->hasMany(FormFieldResponse::class);
    }

    public function submissionReviewers()
    {
        return $this->hasMany(SubmissionReviewer::class);
    }

    // public function revisions()
    // {
    //     return $this->hasMany(SubmissionReviewFix::class);
    // }

    // public function latestRevision()
    // {
    //     return $this->hasOne(SubmissionReviewFix::class)->latest();
    // }

    // Helper methods
    // public function allReviewersApproved()
    // {
    //     $totalReviewers = $this->submissionReviewers()->count();
    //     $approvedReviewers = $this->submissionReviewers()
    //         ->whereHas('submissionReview', function ($q) {
    //             $q->where('decision', 'approved');
    //         })
    //         ->count();

    //     return $totalReviewers > 0 && $totalReviewers === $approvedReviewers;
    // }

    public function canProceed()
    {
        return $this->status === SubmissionStatus::APPROVED && $this->allReviewersApproved();
    }

    public function needsRevision()
    {
        return $this->status === SubmissionStatus::NEEDS_REVISION;
    }

    // private function hasRejectedReviews()
    // {
    //     return $this->submissionReviewers()
    //         ->whereHas('submissionReview', function ($q) {
    //             $q->where('decision', 'needs_revision');
    //         })
    //         ->exists();
    // }

    // private function hasAnyReviews()
    // {
    //     return $this->submissionReviewers()
    //         ->whereHas('submissionReview')
    //         ->exists();
    // }

    // Scopes
    public function scopeByStatus($query, SubmissionStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', SubmissionStatus::PENDING);
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', SubmissionStatus::UNDER_REVIEW);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', SubmissionStatus::APPROVED);
    }

    // Add these relations to existing FormSubmission model
    public function reviewSummaries()
    {
        return $this->hasMany(ReviewSummary::class);
    }

    public function reviewComments()
    {
        return $this->hasMany(ReviewComment::class, 'review_summary_id', 'id')
            ->join('review_summaries', 'review_comments.review_summary_id', '=', 'review_summaries.id')
            ->where('review_summaries.form_submission_id', $this->id);
    }

    // Alternative approach for reviewComments relation
    public function allReviewComments()
    {
        return $this->hasManyThrough(
            ReviewComment::class,
            ReviewSummary::class,
            'form_submission_id', // Foreign key on review_summaries table
            'review_summary_id',  // Foreign key on review_comments table
            'id',                 // Local key on form_submissions table
            'id'                  // Local key on review_summaries table
        );
    }

    // Updated helper methods using ReviewSummary instead of SubmissionReview
    public function allReviewersApproved()
    {
        $totalReviewers = $this->reviewSummaries()->count();
        $approvedReviewers = $this->reviewSummaries()
            ->where('status', 'resolved')
            ->count();

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

    public function updateStatusBasedOnReviews()
    {
        if ($this->hasRejectedReviews()) {
            $this->status = SubmissionStatus::REJECTED;
        } else if ($this->hasRevisionsRequested()) {
            $this->status = SubmissionStatus::NEEDS_REVISION;
        } else if ($this->allReviewersApproved()) {
            $this->status = SubmissionStatus::APPROVED;
        } else if ($this->hasAnyReviews()) {
            $this->status = SubmissionStatus::UNDER_REVIEW;
        } else {
            $this->status = SubmissionStatus::PENDING;
        }

        $this->save();
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
        return $this->reviewSummaries()->firstOrCreate(
            ['reviewer_id' => $reviewerId],
            ['status' => 'open']
        );
    }

    public function removeReviewer(int $reviewerId): bool
    {
        return $this->reviewSummaries()
            ->where('reviewer_id', $reviewerId)
            ->delete() > 0;
    }
}
