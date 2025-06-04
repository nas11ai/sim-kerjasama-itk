<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $fillable = [
        'form_id',
        'label',
        'is_required',
        'field_type_id',
        'order',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function fieldType()
    {
        return $this->belongsTo(FieldType::class);
    }
}
