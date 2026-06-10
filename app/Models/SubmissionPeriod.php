<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * @property bool $is_active
 * @property string $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Collection $form_phases
 * @property-read Collection<int, SubmissionDate> $submissionDates
 * @property-read Collection<int, SubmissionPeriodPhase> $submissionPeriodPhases
 */
class SubmissionPeriod extends Model
{
    protected $fillable = ['name'];

    public function submissionPeriodDetails()
    {
        return $this->hasMany(SubmissionPeriodDetail::class);
    }

    /** @return HasMany<SubmissionDate, $this> */
    public function submissionDates(): HasMany
    {
        return $this->hasMany(SubmissionDate::class);
    }

    /** @return HasMany<SubmissionPeriodPhase, $this> */
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
