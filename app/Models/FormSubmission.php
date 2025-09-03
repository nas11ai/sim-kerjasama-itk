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

    public function revisions()
    {
        return $this->hasMany(SubmissionReviewFix::class);
    }

    public function latestRevision()
    {
        return $this->hasOne(SubmissionReviewFix::class)->latest();
    }

    // Helper methods
    public function allReviewersApproved()
    {
        $totalReviewers = $this->submissionReviewers()->count();
        $approvedReviewers = $this->submissionReviewers()
            ->whereHas('submissionReview', function ($q) {
                $q->where('decision', 'approved');
            })
            ->count();

        return $totalReviewers > 0 && $totalReviewers === $approvedReviewers;
    }

    public function canProceed()
    {
        return $this->status === SubmissionStatus::APPROVED && $this->allReviewersApproved();
    }

    public function needsRevision()
    {
        return $this->status === SubmissionStatus::NEEDS_REVISION;
    }

    public function updateStatusBasedOnReviews()
    {
        if ($this->allReviewersApproved()) {
            $this->status = SubmissionStatus::APPROVED;
        } else if ($this->hasRejectedReviews()) {
            $this->status = SubmissionStatus::NEEDS_REVISION;
        } else if ($this->hasAnyReviews()) {
            $this->status = SubmissionStatus::UNDER_REVIEW;
        } else {
            $this->status = SubmissionStatus::PENDING;
        }

        $this->save();
    }

    private function hasRejectedReviews()
    {
        return $this->submissionReviewers()
            ->whereHas('submissionReview', function ($q) {
                $q->where('decision', 'needs_revision');
            })
            ->exists();
    }

    private function hasAnyReviews()
    {
        return $this->submissionReviewers()
            ->whereHas('submissionReview')
            ->exists();
    }

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
}
