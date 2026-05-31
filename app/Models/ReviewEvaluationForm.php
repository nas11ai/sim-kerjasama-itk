<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewEvaluationForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'form_phase_detail_id', // Changed from form_phase_id
        'is_required',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the form phase detail that owns the review evaluation form.
     *
     * @return BelongsTo<FormPhaseDetail, $this>
     */
    public function formPhaseDetail(): BelongsTo
    {
        return $this->belongsTo(FormPhaseDetail::class, 'form_phase_detail_id');
    }

    /**
     * Get the review form fields for the evaluation form.
     *
     * @return HasMany<ReviewFormField, $this>
     */
    public function reviewFormFields(): HasMany
    {
        return $this->hasMany(ReviewFormField::class);
    }

    /**
     * Get the reviewer form assignments for the evaluation form.
     *
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function reviewerFormAssignments(): HasMany
    {
        return $this->hasMany(ReviewerFormAssignment::class);
    }

    /**
     * Scope a query to only include active forms.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include required forms.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope a query to order by the order column.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
