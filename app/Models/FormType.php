<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function forms()
    {
        return $this->hasMany(Form::class);
    }

    public function formFields()
    {
        return $this->hasMany(FormField::class);
    }
}
