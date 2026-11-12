<?php

namespace App\Models;

use App\Support\PhaseAccessGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $submission_reviewer_id
 * @property int $review_evaluation_form_id
 * @property-read SubmissionReviewer $submissionReviewer
 * @property-read ReviewEvaluationForm $reviewEvaluationForm
 * @property-read ReviewFormResponse|null $reviewFormResponse
 */
class ReviewerFormAssignment extends Model
{
    /**
     * @property-read ReviewFormResponse|null $reviewFormResponse
     */
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

    /**
     * @return BelongsTo<SubmissionReviewer, $this>
     */
    public function submissionReviewer(): BelongsTo
    {
        return $this->belongsTo(SubmissionReviewer::class);
    }

    /**
     * @return BelongsTo<ReviewEvaluationForm, $this>
     */
    public function reviewEvaluationForm(): BelongsTo
    {
        return $this->belongsTo(ReviewEvaluationForm::class);
    }

    /**
     * @return HasOne<ReviewFormResponse, $this>
     */
    public function reviewFormResponse(): HasOne
    {
        return $this->hasOne(ReviewFormResponse::class);
    }

    // Scopes
    public function scopeActive($query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired($query): Builder
    {
        return $query->where('is_required', true);
    }

    // Helper methods
    public function isOverdue(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        return $this->due_date->isPast() && !$this->isCompleted();
    }

    // BR-FE-17, BR-FE-08 — tenggat evaluasi bisa dibuka kembali hanya lewat override detail pemiliknya (ADR 0014, W25).
    public function isClosedByDeadline(): bool
    {
        if (!$this->isOverdue()) {
            return false;
        }

        $submission = $this->submissionReviewer->formSubmission;
        // BR-FE-10 — override langkah pengajuan yang dinilai; pengajuan tanpa langkah tidak punya pintu override.
        $detail = $submission->formPhaseDetail;
        if ($detail === null) {
            return true;
        }

        return PhaseAccessGate::activeOverride($submission, $detail) === null;
    }

    public function isCompleted(): bool
    {
        return $this->reviewFormResponse?->status === 'submitted';
    }

    public function getDaysUntilDue(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return (int) now()->diffInDays($this->due_date, false);
    }
}
