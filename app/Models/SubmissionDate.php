<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionDate extends Model
{
    protected $fillable = [
        'submission_date_label_id',
        'datetime',
        'submission_period_id',
    ];

    public function submissionPeriod()
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    public function submissionDateLabel()
    {
        return $this->belongsTo(SubmissionDateLabel::class);
    }
}
