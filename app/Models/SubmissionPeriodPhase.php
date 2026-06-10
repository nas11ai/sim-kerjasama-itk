<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read FormPhase|null $formPhase
 */
class SubmissionPeriodPhase extends Model
{
    protected $fillable = ['submission_period_id', 'form_phase_id'];

    public function submissionPeriod()
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /** @return BelongsTo<FormPhase, $this> */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }
}
