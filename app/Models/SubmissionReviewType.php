<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionReviewType extends Model
{
    protected $fillable = ['name'];

    public function submissionReviewers()
    {
        return $this->hasMany(SubmissionReviewer::class);
    }
}
