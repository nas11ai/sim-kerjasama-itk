<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewerRole extends Model
{
    protected $fillable = ['name'];

    public function reviewers()
    {
        return $this->hasMany(Reviewer::class);
    }
}
