<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Versi Form Evaluasi yang terbit (BR-REV-18): tidak pernah berubah, jadi jawaban lama tetap
 * ber-FK ke field yang persis diisinya. Hapus hanya lewat cascade saat formnya dihapus.
 *
 * @property int $id
 * @property int $review_evaluation_form_id
 * @property int $number
 * @property int|null $published_by
 * @property Carbon $published_at
 * @property-read ReviewEvaluationForm $reviewEvaluationForm
 * @property-read User|null $publisher
 */
class ReviewEvaluationFormVersion extends Model
{
    protected $fillable = [
        'review_evaluation_form_id',
        'number',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'number' => 'integer',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Versi form evaluasi yang terbit tidak dapat diubah.');
        });
    }

    /** @return BelongsTo<ReviewEvaluationForm, $this> */
    public function reviewEvaluationForm(): BelongsTo
    {
        return $this->belongsTo(ReviewEvaluationForm::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<ReviewFormField, $this> */
    public function reviewFormFields(): HasMany
    {
        return $this->hasMany(ReviewFormField::class)->orderBy('order')->orderBy('id');
    }

    public function label(): string
    {
        return self::labelFor($this->number);
    }

    // Juga untuk versi yang belum lahir: konfirmasi Terbitkan menyebut label berikutnya (§4.5).
    public static function labelFor(int $number): string
    {
        return "Versi {$number}";
    }
}
