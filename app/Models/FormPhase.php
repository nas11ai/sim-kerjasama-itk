<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormPhase extends Model
{
    protected $fillable = ['title', 'description', 'is_active'];

    public function formPhaseDetails()
    {
        return $this->hasMany(FormPhaseDetail::class);
    }

    public function submissionPeriodPhases()
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }
}
