<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $datetime
 * @property-read SubmissionDateLabel|null $submissionDateLabel
 */
class SubmissionDate extends Model
{
    protected $fillable = [
        'submission_date_label_id',
        'date',
        'submission_period_id',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function submissionPeriod()
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /** @return BelongsTo<SubmissionDateLabel, $this> */
    public function submissionDateLabel(): BelongsTo
    {
        return $this->belongsTo(SubmissionDateLabel::class);
    }

    public function isPassed(): bool
    {
        return Carbon::now()->gt($this->date);
    }

    public function isToday(): bool
    {
        return Carbon::now()->isSameDay($this->date);
    }

    public function isUpcoming(): bool
    {
        return Carbon::now()->lt($this->date);
    }

    public function getDaysFromNow(): int
    {
        return (int) Carbon::now()->diffInDays($this->date, false);
    }
}
