<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $form_submission_id
 * @property int|null $reviewer_id
 * @property string $status
 * @property string|null $summary_notes
 * @property-read FormSubmission $formSubmission
 * @property-read Reviewer|null $reviewer
 * @property-read Collection<int, ReviewSummaryAttachment> $attachments
 */
class ReviewSummary extends Model
{
    protected $fillable = [
        'form_submission_id',
        'reviewer_id',
        'status',
        'summary_notes',
    ];

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

    // NEW: Check if discussions should be allowed for this review
    public function discussionsAllowed(): bool
    {
        if (!$this->reviewer_id) {
            return true; // General discussions always allowed
        }

        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $this->form_submission_id,
            'reviewer_id' => $this->reviewer_id,
        ])->first();

        return $submissionReviewer ? $submissionReviewer->hasSettledEvaluation() : true;
    }
}
