<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionDate extends Model
{
    protected $fillable = [
        'label',
        'date',
        'submission_period_id',
    ];

    public function submissionPeriod()
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }
}
