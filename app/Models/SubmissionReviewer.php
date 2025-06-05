<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionReviewer extends Model
{
    protected $fillable = ['form_submission_id', 'reviewer_id', 'submission_review_type_id'];

    public function formSubmission()
    {
        return $this->belongsTo(FormSubmission::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Reviewer::class);
    }

    public function submissionReviewType()
    {
        return $this->belongsTo(SubmissionReviewType::class);
    }
}
