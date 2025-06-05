<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhaseType extends Model
{
    protected $fillable = ['name'];

    public function formPhaseDetails()
    {
        return $this->hasMany(FormPhaseDetail::class);
    }
}
