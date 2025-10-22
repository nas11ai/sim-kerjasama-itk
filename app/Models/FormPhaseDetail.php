<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $casts = [
        'needs_review' => 'boolean',
    ];

    /**
     * Get the form phase that owns the detail.
     */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }

    /**
     * Get the form access control for the detail.
     */
    public function formAccessControl(): BelongsTo
    {
        return $this->belongsTo(FormAccessControl::class);
    }

    /**
     * Get the phase type for the detail.
     */
    public function phaseType(): BelongsTo
    {
        return $this->belongsTo(PhaseType::class);
    }

    /**
     * Get the review evaluation forms for this form phase detail.
     */
    public function reviewEvaluationForms(): HasMany
    {
        return $this->hasMany(ReviewEvaluationForm::class, 'form_phase_detail_id');
    }

    /**
     * Get active review evaluation forms.
     */
    public function activeReviewEvaluationForms()
    {
        return $this->reviewEvaluationForms()->active()->ordered();
    }

    /**
     * Check if this detail has review evaluation forms.
     */
    public function hasReviewEvaluationForms(): bool
    {
        return $this->reviewEvaluationForms()->exists();
    }

    /**
     * Scope a query to order by the order column.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
