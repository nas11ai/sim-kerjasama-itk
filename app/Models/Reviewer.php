<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reviewer extends Model
{
    protected $fillable = [
        'user_id',
        'reviewer_role_id',
        'start_date',
        'end_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewerRole()
    {
        return $this->belongsTo(ReviewerRole::class);
    }

    public function submissionReviewers()
    {
        return $this->hasMany(SubmissionReviewer::class);
    }
}
