<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewerFormAssignment extends Model
{
    protected $fillable = [
        'submission_reviewer_id',
        'review_evaluation_form_id',
        'is_required',
        'assigned_at',
        'due_date',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
        'due_date' => 'datetime',
    ];

    public function submissionReviewer(): BelongsTo
    {
        return $this->belongsTo(SubmissionReviewer::class);
    }

    public function reviewEvaluationForm(): BelongsTo
    {
        return $this->belongsTo(ReviewEvaluationForm::class);
    }

    public function reviewFormResponse(): HasOne
    {
        return $this->hasOne(ReviewFormResponse::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereDoesntHave('reviewFormResponse', function ($q) {
                $q->where('status', 'submitted');
            });
    }

    public function scopeDueSoon($query, int $hours = 24)
    {
        return $query->whereBetween('due_date', [now(), now()->addHours($hours)])
            ->whereDoesntHave('reviewFormResponse', function ($q) {
                $q->where('status', 'submitted');
            });
    }

    // Helper methods
    public function isOverdue(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        return $this->due_date->isPast() && !$this->isCompleted();
    }

    public function isDueSoon(int $hours = 24): bool
    {
        if (!$this->due_date || $this->isCompleted()) {
            return false;
        }

        return $this->due_date->isBetween(now(), now()->addHours($hours));
    }

    public function isCompleted(): bool
    {
        return $this->reviewFormResponse?->status === 'submitted';
    }

    public function getStatus(): string
    {
        if (!$this->reviewFormResponse) {
            return 'not_started';
        }

        return $this->reviewFormResponse->status;
    }

    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            'not_started' => 'Not Started',
            'draft' => 'In Progress',
            'submitted' => 'Completed',
            'locked' => 'Locked',
            default => 'Unknown'
        };
    }

    public function getDaysUntilDue(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    public function getHoursUntilDue(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->diffInHours($this->due_date, false);
    }

    // Check if assignment can be completed (not locked and active)
    public function canBeCompleted(): bool
    {
        return $this->is_active &&
            (!$this->due_date || !$this->due_date->isPast()) &&
            $this->getStatus() !== 'locked';
    }

    // Auto-lock if past due date
    public function autoLockIfOverdue(): void
    {
        if ($this->isOverdue() && $this->reviewFormResponse) {
            $this->reviewFormResponse->update([
                'status' => 'locked',
                'locked_at' => now()
            ]);
        }
    }
}
