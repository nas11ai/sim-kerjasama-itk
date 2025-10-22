<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewEvaluationForm extends Model
{
    protected $fillable = [
        'title',
        'description',
        'is_required',
        'form_phase_id',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }

    public function reviewFormFields(): HasMany
    {
        return $this->hasMany(ReviewFormField::class);
    }

    public function reviewerFormAssignments(): HasMany
    {
        return $this->hasMany(ReviewerFormAssignment::class);
    }

    public function reviewFormResponses(): HasMany
    {
        return $this->hasMany(ReviewFormResponse::class, 'reviewer_form_assignment_id');
    }

    // Scope for active forms
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for required forms
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    // Scope ordered
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    // Check if form has any field options
    public function hasFieldOptions(): bool
    {
        return $this->reviewFormFields()
            ->whereHas('reviewFormFieldOptions')
            ->exists();
    }

    // Get total number of fields
    public function getFieldsCountAttribute(): int
    {
        return $this->reviewFormFields()->count();
    }

    // Get required fields count
    public function getRequiredFieldsCountAttribute(): int
    {
        return $this->reviewFormFields()->where('is_required', true)->count();
    }
}
