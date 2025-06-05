<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionPeriodPhase extends Model
{
    protected $fillable = ['submission_period_id', 'form_phase_id'];

    public function submissionPeriod()
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    public function formPhase()
    {
        return $this->belongsTo(FormPhase::class);
    }
}
