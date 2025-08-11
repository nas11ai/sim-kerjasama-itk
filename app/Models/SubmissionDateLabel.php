<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionDateLabel extends Model
{
    protected $fillable = ['label'];

    public function submissionDates()
    {
        return $this->hasMany(SubmissionDate::class);
    }
}
