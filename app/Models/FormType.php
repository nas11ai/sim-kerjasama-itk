<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
