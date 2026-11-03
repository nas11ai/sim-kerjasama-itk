<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $form_phase_id
 * @property int $submission_period_id
 * @property-read FormPhase|null $formPhase
 * @property-read SubmissionPeriod|null $submissionPeriod
 */
class SubmissionPeriodPhase extends Model
{
    protected $fillable = ['submission_period_id', 'form_phase_id'];

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /** @return BelongsTo<FormPhase, $this> */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }
}
