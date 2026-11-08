<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $form_submission_id
 * @property int $reviewer_id
 * @property int $author_reviewer_id
 * @property string $status
 * @property string|null $summary_notes
 * @property-read FormSubmission $formSubmission
 * @property-read Reviewer|null $reviewer
 * @property-read Reviewer $author
 * @property-read Collection<int, ReviewSummaryAttachment> $attachments
 */
class ReviewSummary extends Model
{
    // `author_reviewer_id` sengaja tidak fillable: hanya diisi sekali, saat dibuat.
    protected $fillable = [
        'form_submission_id',
        'reviewer_id',
        'status',
        'summary_notes',
    ];

    /**
     * BR-REV-15 — penulis ulir adalah pemegangnya saat ulir dibuka, dan tidak pernah berubah.
     * `reviewer_id` (pemegang) boleh berpindah ke reviewer pengganti; penulis tetap.
     */
    protected static function booted(): void
    {
        static::creating(function (self $summary): void {
            $summary->author_reviewer_id = $summary->reviewer_id;
        });
    }

    /** @return BelongsTo<Reviewer, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Reviewer::class, 'author_reviewer_id');
    }

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /** @return BelongsTo<Reviewer, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Reviewer::class);
    }

    /** @return HasMany<ReviewSummaryAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReviewSummaryAttachment::class);
    }

    public function reviewComments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }
}
