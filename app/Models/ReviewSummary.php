<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewSummary extends Model
{
    protected $fillable = [
        'form_submission_id',
        'reviewer_id',
        'status',
        'summary_notes',
    ];

    public function formSubmission()
    {
        return $this->belongsTo(FormSubmission::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Reviewer::class);
    }
}
