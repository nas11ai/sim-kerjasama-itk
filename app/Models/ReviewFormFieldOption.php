<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property int $id
 * @property string $label
 * @property string|null $value
 */
class ReviewFormFieldOption extends Model
{
    // Cermin BR-FE-24: opsi yang dibuang berhenti ditawarkan, tapi jawaban lama tetap terbaca.
    use SoftDeletes;

    // BR-REV-18 — opsi milik field versi yang terbit tidak berubah.
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Opsi versi formulir evaluasi yang terbit tidak dapat diubah.');
        });
    }

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
