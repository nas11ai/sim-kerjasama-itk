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
}
