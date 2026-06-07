<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $review_evaluation_forms_count
 * @property int $required_review_evaluation_forms_count
 * @property-read SubmissionPeriod|null $submissionPeriod
 */
class FormPhase extends Model
{
    protected $fillable = ['title', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<FormPhaseDetail, $this>
     */
    public function formPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class);
    }

    public function submissionPeriodPhases(): HasMany
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }
}
