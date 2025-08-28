<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $fillable = ['title', 'description', 'is_active', 'form_type_id'];

    public function formType()
    {
        return $this->belongsTo(FormType::class);
    }

    public function formFields()
    {
        return $this->hasMany(FormField::class);
    }

    public function formAccessControls()
    {
        return $this->hasMany(FormAccessControl::class);
    }
}
