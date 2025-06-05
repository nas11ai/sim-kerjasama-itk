<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionReviewFix extends Model
{
    protected $fillable = [
        'submission_review_id',
        'revision_note',
        'file_path',
        'submitted_by',
    ];

    public function submissionReview()
    {
        return $this->belongsTo(SubmissionReview::class, 'submission_review_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
