<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $reviewer_id
 * @property int $form_submission_id
 * @property string $status
 * @property-read FormSubmission $formSubmission
 * @property-read Reviewer $reviewer
 */
class SubmissionReviewer extends Model
{
    protected $fillable = [
        'form_submission_id',
        'reviewer_id',
        'evaluation_status',
        'status',
    ];

    protected $casts = [
        'evaluation_status' => 'string',
        'status' => 'string',
    ];

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /**
     * @return BelongsTo<Reviewer, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Reviewer::class);
    }

    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function reviewerFormAssignments(): HasMany
    {
        return $this->hasMany(ReviewerFormAssignment::class);
    }

    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function activeReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()->active();
    }

    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function completedReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()
            ->active()
            ->whereHas('reviewFormResponse', function ($query) {
                $query->where('status', 'submitted');
            });
    }

    /**
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function pendingReviewerFormAssignments(): HasMany
    {
        return $this->reviewerFormAssignments()
            ->active()
            ->whereDoesntHave('reviewFormResponse', function ($query) {
                $query->where('status', 'submitted');
            });
    }

    /** Penugasan yang masih berlaku; yang `replaced` adalah sejarah. */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * BR-REV-05 — satu definisi "belum selesai", dipakai `canProceed()` dan
     * `PendingEvaluationCheck`. Bukan `= 'pending'`: status evaluasi yang
     * kelak ditambahkan otomatis terhitung belum selesai, bukan diam-diam beres.
     *
     * @param  Builder<SubmissionReviewer>  $query
     * @return Builder<SubmissionReviewer>
     */
    public function scopeUnsettledEvaluation(Builder $query): Builder
    {
        return $query->active()->whereNotIn('evaluation_status', self::SETTLED_EVALUATION);
    }

    /**
     * Status evaluasi yang dihitung selesai untuk BR-REV-05, BR-REV-06, dan
     * BR-FE-06. `not_required` ikut karena reviewer yang memang tidak wajib
     * menilai tak akan pernah selesai, dan submission-nya terkunci selamanya.
     */
    public const SETTLED_EVALUATION = ['completed', 'not_required'];

    /** Apakah evaluasi penugasan ini sudah tidak ditunggu lagi. */
    public function hasSettledEvaluation(): bool
    {
        return in_array($this->evaluation_status, self::SETTLED_EVALUATION, true);
    }

    public function markAsReplaced(): void
    {
        $this->update(['status' => 'replaced']);
    }

    public function getPendingFormsCountAttribute(): int
    {
        return $this->pendingReviewerFormAssignments()->count();
    }
}
