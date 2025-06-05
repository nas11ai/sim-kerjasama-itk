<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionPeriod extends Model
{
    protected $fillable = ['name'];

    public function submissionPeriodDetails()
    {
        return $this->hasMany(SubmissionPeriodDetail::class);
    }
}
