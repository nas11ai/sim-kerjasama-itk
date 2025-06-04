<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $fillable = [
        'form_id',
        'submitted_by'
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function formFieldResponses()
    {
        return $this->hasMany(FormFieldResponse::class);
    }
}
