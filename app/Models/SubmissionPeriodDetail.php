<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionPeriodDetail extends Model
{
    protected $fillable = ['submission_period_id', 'submission_rule_id'];

    public function submissionPeriod()
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    public function submissionRule()
    {
        return $this->belongsTo(SubmissionRule::class);
    }
}
