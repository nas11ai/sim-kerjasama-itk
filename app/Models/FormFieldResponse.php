<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormFieldResponse extends Model
{
    protected $fillable = [
        'form_submission_id',
        'form_field_id',
        'value',
    ];

    public function formSubmission()
    {
        return $this->belongsTo(FormSubmission::class);
    }

    public function formField()
    {
        return $this->belongsTo(FormField::class);
    }
}
