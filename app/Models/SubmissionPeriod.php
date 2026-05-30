<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * @property bool $is_active
 * @property string $status
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property \Illuminate\Database\Eloquent\Collection $form_phases
 * @property \Illuminate\Support\Collection $submissionDates
 * @property \Illuminate\Support\Collection $submissionPeriodPhases
 */
class SubmissionPeriod extends Model
{

    protected $fillable = ['name'];

    public function submissionPeriodDetails()
    {
        return $this->hasMany(SubmissionPeriodDetail::class);
    }


    public function submissionDates(): HasMany
    {
        return $this->hasMany(SubmissionDate::class);
    }

    public function submissionPeriodPhases(): HasMany
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }

    public function forceClose(): void
    {
        $this->update([
            'is_force_closed' => true,
        ]);

        Log::info('Submission period force closed', [
            'submission_period_id' => $this->id,
            'submission_period_name' => $this->name,
            'closed_at' => now(),
        ]);
    }

}
