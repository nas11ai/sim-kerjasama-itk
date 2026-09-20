<?php

namespace App\Models;

use App\Support\Review\FieldResponseFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $value
 * @property-read ReviewFormField|null $reviewFormField
 */
class ReviewFormFieldResponse extends Model
{
    protected $fillable = [
        'review_form_response_id',
        'review_form_field_id',
        'value',
    ];

    /**
     * @return BelongsTo<ReviewFormResponse, $this>
     */
    public function reviewFormResponse(): BelongsTo
    {
        return $this->belongsTo(ReviewFormResponse::class);
    }

    /**
     * @return BelongsTo<ReviewFormField, $this>
     */
    public function reviewFormField(): BelongsTo
    {
        return $this->belongsTo(ReviewFormField::class);
    }

    // Check if response has value
    public function hasValue(): bool
    {
        return !empty(trim($this->value));
    }

    public function getFormattedValueAttribute(): string
    {
        return FieldResponseFormatter::readable($this);
    }

    // Validate field response based on field requirements
    public function validate(): array
    {
        $errors = [];
        $field = $this->reviewFormField;

        // Check if required field has value
        if ($field->is_required && !$this->hasValue()) {
            $errors[] = "Field '{$field->label}' is required";
        }

        // Field type specific validation
        if ($this->hasValue()) {
            $fieldType = $field->fieldType->name ?? '';

            switch ($fieldType) {
                case 'email':
                    if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Field '{$field->label}' must be a valid email address";
                    }
                    break;

                case 'url':
                    if (!filter_var($this->value, FILTER_VALIDATE_URL)) {
                        $errors[] = "Field '{$field->label}' must be a valid URL";
                    }
                    break;

                case 'number':
                    if (!is_numeric($this->value)) {
                        $errors[] = "Field '{$field->label}' must be a number";
                    }
                    break;
            }

            // Custom validation rules
            if ($field->hasValidationRules()) {
                $customErrors = $this->validateCustomRules($field->validation_rules);
                $errors = array_merge($errors, $customErrors);
            }
        }

        return $errors;
    }

    // Validate custom rules
    protected function validateCustomRules(array $rules): array
    {
        $errors = [];
        $field = $this->reviewFormField;

        foreach ($rules as $rule => $value) {
            switch ($rule) {
                case 'min_length':
                    if (strlen($this->value) < $value) {
                        $errors[] = "Field '{$field->label}' must be at least {$value} characters";
                    }
                    break;

                case 'max_length':
                    if (strlen($this->value) > $value) {
                        $errors[] = "Field '{$field->label}' must not exceed {$value} characters";
                    }
                    break;

                case 'min_value':
                    if (is_numeric($this->value) && (float) $this->value < $value) {
                        $errors[] = "Field '{$field->label}' must be at least {$value}";
                    }
                    break;

                case 'max_value':
                    if (is_numeric($this->value) && (float) $this->value > $value) {
                        $errors[] = "Field '{$field->label}' must not exceed {$value}";
                    }
                    break;
            }
        }

        return $errors;
    }
}
