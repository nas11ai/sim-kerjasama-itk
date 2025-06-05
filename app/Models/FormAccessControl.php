<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class FormAccessControl extends Model
{
    protected $fillable = ['form_id', 'role_id', 'study_program_id'];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function studyProgram()
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function formPhaseDetails()
    {
        return $this->hasMany(FormPhaseDetail::class);
    }
}
