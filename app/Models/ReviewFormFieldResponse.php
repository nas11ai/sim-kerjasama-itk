<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewFormFieldResponse extends Model
{
    protected $fillable = [
        'review_form_response_id',
        'review_form_field_id',
        'value',
    ];

    public function reviewFormResponse(): BelongsTo
    {
        return $this->belongsTo(ReviewFormResponse::class);
    }

    public function reviewFormField(): BelongsTo
    {
        return $this->belongsTo(ReviewFormField::class);
    }

    // Check if response has value
    public function hasValue(): bool
    {
        return !empty(trim($this->value));
    }

    // Get formatted value based on field type
    public function getFormattedValueAttribute(): string
    {
        if (!$this->hasValue()) {
            return '';
        }

        $fieldType = $this->reviewFormField->fieldType->name ?? '';

        return match ($fieldType) {
            'checkbox' => $this->formatCheckboxValue(),
            'radio', 'select' => $this->formatSelectValue(),
            'number' => $this->formatNumberValue(),
            'date' => $this->formatDateValue(),
            'email' => $this->formatEmailValue(),
            'url' => $this->formatUrlValue(),
            default => $this->value
        };
    }

    // Format checkbox values (comma-separated if multiple)
    protected function formatCheckboxValue(): string
    {
        $values = json_decode($this->value, true);
        if (is_array($values)) {
            return implode(', ', $values);
        }
        return $this->value;
    }

    // Format select/radio values (show label if available)
    protected function formatSelectValue(): string
    {
        $option = $this->reviewFormField->reviewFormFieldOptions()
            ->where('value', $this->value)
            ->orWhere('label', $this->value)
            ->first();

        return $option ? $option->label : $this->value;
    }

    // Format number values
    protected function formatNumberValue(): string
    {
        if (is_numeric($this->value)) {
            return number_format((float) $this->value, 2);
        }
        return $this->value;
    }

    // Format date values
    protected function formatDateValue(): string
    {
        try {
            return \Carbon\Carbon::parse($this->value)->format('d M Y');
        } catch (\Exception $e) {
            return $this->value;
        }
    }

    // Format email values
    protected function formatEmailValue(): string
    {
        return filter_var($this->value, FILTER_VALIDATE_EMAIL) ? $this->value : $this->value;
    }

    // Format URL values
    protected function formatUrlValue(): string
    {
        if (filter_var($this->value, FILTER_VALIDATE_URL)) {
            return "<a href='{$this->value}' target='_blank' class='text-blue-600 hover:underline'>{$this->value}</a>";
        }
        return $this->value;
    }

    // Get value for editing (raw value)
    public function getEditValueAttribute(): string
    {
        return $this->value ?? '';
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
