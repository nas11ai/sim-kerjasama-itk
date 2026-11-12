<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reviewer_form_assignment_id
 * @property int $review_evaluation_form_version_id BR-REV-18 — versi yang diisi jawaban ini
 * @property string $status
 * @property string|null $final_notes
 * @property Carbon|null $submitted_at
 * @property Carbon|null $locked_at
 * @property Carbon|null $superseded_at BR-REV-19 — terisi = riwayat, digantikan versi baru
 * @property-read ReviewerFormAssignment $reviewerFormAssignment
 * @property-read ReviewEvaluationFormVersion $reviewEvaluationFormVersion
 * @property-read Collection<int, ReviewFormFieldResponse> $reviewFormFieldResponses
 */
class ReviewFormResponse extends Model
{
    protected $fillable = [
        'reviewer_form_assignment_id',
        'review_evaluation_form_version_id',
        'status',
        'submitted_at',
        'locked_at',
        'superseded_at',
        'final_notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ReviewerFormAssignment, $this>
     */
    public function reviewerFormAssignment(): BelongsTo
    {
        return $this->belongsTo(ReviewerFormAssignment::class);
    }

    /** @return BelongsTo<ReviewEvaluationFormVersion, $this> */
    public function reviewEvaluationFormVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewEvaluationFormVersion::class);
    }

    /**
     * @return HasMany<ReviewFormFieldResponse, $this>
     */
    public function reviewFormFieldResponses(): HasMany
    {
        return $this->hasMany(ReviewFormFieldResponse::class);
    }

    /**
     * BR-REV-19 — jawaban berlaku atas Form Evaluasi pin itu dari kursi aktif angkatan × langkahnya:
     * yang dibuka ulang saat versi baru diterapkan. Lembar reviewer yang digantikan sudah menjadi
     * riwayat (BR-REV-10) dan tidak diisi ulang siapa pun.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrentInCohortOf(Builder $query, EvaluationFormPin $pin): Builder
    {
        return $query
            ->whereNull('superseded_at')
            ->whereHas('reviewerFormAssignment', fn (Builder $assignment) => $assignment
                ->where('review_evaluation_form_id', $pin->review_evaluation_form_id)
                ->whereHas('submissionReviewer', fn (Builder $seat) => $seat
                    ->where('status', 'active')
                    ->whereHas('formSubmission', fn (Builder $submission) => $submission
                        ->where('submission_period_id', $pin->submission_period_id)
                        ->where('form_phase_detail_id', $pin->form_phase_detail_id))));
    }

    // Status checks
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === 'draft' && $this->hasRequiredFieldsCompleted();
    }

    // Check if all required fields are completed
    public function hasRequiredFieldsCompleted(): bool
    {
        $requiredFields = $this->reviewEvaluationFormVersion->reviewFormFields()->where('is_required', true)->get();

        foreach ($requiredFields as $field) {
            $response = $this->reviewFormFieldResponses()
                ->where('review_form_field_id', $field->id)
                ->first();

            if (!$response || empty(trim($response->value))) {
                return false;
            }
        }

        return true;
    }

    // Save field response
    public function saveFieldResponse(int $fieldId, ?string $value): ReviewFormFieldResponse
    {
        return $this->reviewFormFieldResponses()->updateOrCreate(
            ['review_form_field_id' => $fieldId],
            ['value' => $value]
        );
    }
}
