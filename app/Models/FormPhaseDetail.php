<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $reviewEvaluationFormsCount
 * @property int $requiredReviewEvaluationFormsCount
 */
class FormPhaseDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_phase_id',
        'form_access_control_id',
        'phase_type_id',
        'order',
        'needs_review',
    ];

    protected $appends = [
        'review_evaluation_forms_count',
        'required_review_evaluation_forms_count',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
    ];

    /**
     * Get the form phase that owns the detail.
     *
     * @return BelongsTo<FormPhase, $this>
     */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }

    /**
     * Get the form access control for the detail.
     *
     * @return BelongsTo<FormAccessControl, $this>
     */
    public function formAccessControl(): BelongsTo
    {
        return $this->belongsTo(FormAccessControl::class);
    }

    /**
     * Get the phase type for the detail.
     *
     * @return BelongsTo<PhaseType, $this>
     */
    public function phaseType(): BelongsTo
    {
        return $this->belongsTo(PhaseType::class);
    }

    /**
     * Get the review evaluation forms for this form phase detail.
     *
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    // NEW: Review evaluation forms relationship
    public function reviewEvaluationForms(): HasMany
    {
        return $this->hasMany(ReviewEvaluationForm::class);
    }

    // NEW: Active review evaluation forms
    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    public function activeReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->active()->ordered();
    }

    // NEW: Required review evaluation forms
    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    public function requiredReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->required()->active()->ordered();
    }

    // NEW: Optional review evaluation forms
    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
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
        return static::query()
            ->where('form_phase_id', $this->form_phase_id)
            ->where('needs_review', true)
            ->exists();
    }

    // NEW: Check if phase requires evaluation forms to be completed before discussion
    public function requiresEvaluationCompletion(): bool
    {
        return $this->hasReviewEvaluationForms() && $this->needsReview();
    }

    /**
     * Scope a query to order by the order column.
     */

    public function isWithinDeadline(): bool
    {
        $period = $this->formPhase->submissionPeriod;
        if ($period->is_force_closed)
            return false;
        return now()->lte($this->submissionDate->datetime);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
