<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormPhase extends Model
{
    protected $fillable = ['title', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function formPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class);
    }

    public function submissionPeriodPhases(): HasMany
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }

    // NEW: Review evaluation forms relationship
    public function reviewEvaluationForms(): HasMany
    {
        return $this->hasMany(ReviewEvaluationForm::class);
    }

    // NEW: Active review evaluation forms
    public function activeReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->active()->ordered();
    }

    // NEW: Required review evaluation forms
    public function requiredReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->required()->active()->ordered();
    }

    // NEW: Optional review evaluation forms
    public function optionalReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()
            ->where('is_required', false)
            ->active()
            ->ordered();
    }

    // NEW: Check if phase has review evaluation forms
    public function hasReviewEvaluationForms(): bool
    {
        return $this->activeReviewEvaluationForms()->exists();
    }

    // NEW: Get count of review evaluation forms
    public function getReviewEvaluationFormsCountAttribute(): int
    {
        return $this->activeReviewEvaluationForms()->count();
    }

    // NEW: Get count of required review evaluation forms
    public function getRequiredReviewEvaluationFormsCountAttribute(): int
    {
        return $this->requiredReviewEvaluationForms()->count();
    }

    // Check if any form phase details need review
    public function needsReview(): bool
    {
        return $this->formPhaseDetails()->where('needs_review', true)->exists();
    }

    // NEW: Check if phase requires evaluation forms to be completed before discussion
    public function requiresEvaluationCompletion(): bool
    {
        return $this->hasReviewEvaluationForms() && $this->needsReview();
    }
}
