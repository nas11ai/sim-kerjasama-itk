<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\SubmissionPeriodPhase[] $submissionPeriodPhases
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
}
