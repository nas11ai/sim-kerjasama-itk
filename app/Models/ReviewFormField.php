<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $is_required
 * @property string $label
 * @property array|null $validation_rules
 * @property-read FieldType|null $fieldType
 */
class ReviewFormField extends Model
{
    protected $fillable = [
        'review_evaluation_form_id',
        'field_type_id',
        'label',
        'description',
        'is_required',
        'order',
        'validation_rules',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_rules' => 'array',
    ];

    /**
     * @return BelongsTo<ReviewEvaluationForm, $this>
     */
    public function reviewEvaluationForm(): BelongsTo
    {
        return $this->belongsTo(ReviewEvaluationForm::class);
    }

    /**
     * @return BelongsTo<FieldType, $this>
     */
    public function fieldType(): BelongsTo
    {
        return $this->belongsTo(FieldType::class);
    }

    /**
     * @return HasMany<ReviewFormFieldOption, $this>
     */
    public function reviewFormFieldOptions(): HasMany
    {
        return $this->hasMany(ReviewFormFieldOption::class);
    }

    /**
     * @return HasMany<ReviewFormFieldResponse, $this>
     */
    public function reviewFormFieldResponses(): HasMany
    {
        return $this->hasMany(ReviewFormFieldResponse::class);
    }

    // Scope for ordered fields
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    // Scope for required fields
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    // Check if this field requires options (select, radio, checkbox)
    public function requiresOptions(): bool
    {
        $fieldTypeName = $this->fieldType?->name;

        return in_array($fieldTypeName, ['select', 'radio', 'checkbox']);
    }

    // Get field type name
    public function getFieldTypeNameAttribute(): ?string
    {
        return $this->fieldType?->name;
    }

    // Check if field has validation rules
    public function hasValidationRules(): bool
    {
        return !empty($this->validation_rules);
    }

    // Get validation rules as string for frontend
    public function getValidationRulesStringAttribute(): string
    {
        if (empty($this->validation_rules)) {
            return '';
        }

        $rules = [];
        foreach ($this->validation_rules as $rule => $value) {
            if (is_bool($value) && $value) {
                $rules[] = $rule;
            } elseif (!is_bool($value)) {
                $rules[] = "$rule:$value";
            }
        }

        return implode('|', $rules);
    }
}
