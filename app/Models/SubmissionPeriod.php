<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SubmissionPeriod extends Model
{
    protected $fillable = ['name', 'is_force_closed',];

    protected $casts = [
        'is_force_closed' => 'boolean',
    ];

    public function submissionPeriodDetails()
    {
        return $this->hasMany(SubmissionPeriodDetail::class);
    }

    public function submissionDates()
    {
        return $this->hasMany(SubmissionDate::class);
    }

    public function submissionPeriodPhases()
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }

    public function forceClose(): bool
    {
        $this->is_force_closed = true;
        $saved = $this->save();

        if ($saved) {
            Log::info('Submission Period Force Closed', [
                'submission_period_id' => $this->id,
                'name' => $this->name,
                'by_user_id' => auth()->id() ?? 'system',
                'closed_at' => now()->toDateTimeString(),
            ]);
        }

        return $saved;
    }

}
