<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormPhaseDetail extends Model
{
    protected $fillable = ['form_phase_id', 'form_access_control_id', 'phase_type_id', 'order', 'needs_review'];

    public function phaseType()
    {
        return $this->belongsTo(PhaseType::class);
    }

    public function formAccessControl()
    {
        return $this->belongsTo(FormAccessControl::class);
    }

    public function formPhase()
    {
        return $this->belongsTo(FormPhase::class);
    }
}
