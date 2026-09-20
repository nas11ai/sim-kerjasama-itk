<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reviewer_form_assignment_id
 * @property string $status
 * @property string|null $final_notes
 * @property Carbon|null $submitted_at
 * @property Carbon|null $locked_at
 * @property-read ReviewerFormAssignment $reviewerFormAssignment
 * @property-read Collection<int, ReviewFormFieldResponse> $reviewFormFieldResponses
 */
class ReviewFormResponse extends Model
{
    protected $fillable = [
        'reviewer_form_assignment_id',
        'status',
        'submitted_at',
        'locked_at',
        'final_notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ReviewerFormAssignment, $this>
     */
    public function reviewerFormAssignment(): BelongsTo
    {
        return $this->belongsTo(ReviewerFormAssignment::class);
    }

    /**
     * @return HasMany<ReviewFormFieldResponse, $this>
     */
    public function reviewFormFieldResponses(): HasMany
    {
        return $this->hasMany(ReviewFormFieldResponse::class);
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

    // Submit the form
    public function submit(): bool
    {
        if (!$this->canBeSubmitted()) {
            return false;
        }

        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Update parent reviewer evaluation status
        $this->updateReviewerEvaluationStatus();

        return true;
    }

    // Lock the form (usually due to deadline)
    public function lock(): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        $this->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        return true;
    }

    // Check if all required fields are completed
    public function hasRequiredFieldsCompleted(): bool
    {
        $reviewForm = $this->reviewerFormAssignment->reviewEvaluationForm;
        $requiredFields = $reviewForm->reviewFormFields()->where('is_required', true)->get();

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

    // Get completion percentage
    public function getCompletionPercentage(): int
    {
        $reviewForm = $this->reviewerFormAssignment->reviewEvaluationForm;
        $totalFields = $reviewForm->reviewFormFields()->count();

        if ($totalFields === 0) {
            return 100;
        }

        $completedFields = $this->reviewFormFieldResponses()
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->count();

        return min(100, round(($completedFields / $totalFields) * 100));
    }

    // Save field response
    public function saveFieldResponse(int $fieldId, ?string $value): ReviewFormFieldResponse
    {
        return $this->reviewFormFieldResponses()->updateOrCreate(
            ['review_form_field_id' => $fieldId],
            ['value' => $value]
        );
    }

    // Update reviewer evaluation status based on all assigned forms
    protected function updateReviewerEvaluationStatus(): void
    {
        $submissionReviewer = $this->reviewerFormAssignment->submissionReviewer;

        // Check if all required forms are completed
        $allRequiredCompleted = $submissionReviewer->reviewerFormAssignments()
            ->required()
            ->active()
            ->whereDoesntHave('reviewFormResponse', function ($q) {
                $q->where('status', 'submitted');
            })
            ->doesntExist();

        if ($allRequiredCompleted) {
            $submissionReviewer->update(['evaluation_status' => 'completed']);
        }
    }
}
