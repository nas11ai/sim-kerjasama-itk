<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $label
 * @property string|null $value
 */
class ReviewFormFieldOption extends Model
{
    protected $fillable = [
        'review_form_field_id',
        'label',
        'value',
        'order',
    ];

    public function reviewFormField(): BelongsTo
    {
        return $this->belongsTo(ReviewFormField::class);
    }

    // Scope for ordered options
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    // Get display value (use value if exists, otherwise label)
    public function getDisplayValueAttribute(): string
    {
        return $this->value ?? $this->label;
    }

    // Get value for storing (use value if exists, otherwise label)
    public function getStorageValueAttribute(): string
    {
        return $this->value ?? $this->label;
    }
}
