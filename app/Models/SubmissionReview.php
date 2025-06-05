<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionReview extends Model
{
    protected $fillable = ['submission_reviewer_id', 'revision_note', 'file_path'];

    public function submissionReviewer()
    {
        return $this->belongsTo(SubmissionReviewer::class);
    }
}
