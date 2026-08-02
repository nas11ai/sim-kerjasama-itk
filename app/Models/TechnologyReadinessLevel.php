<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TechnologyReadinessLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'level',
        'description',
    ];

    public function schemes(): BelongsToMany
    {
        return $this->belongsToMany(
            Scheme::class,
            'scheme_allowed_trls',
            'trl_id',
            'scheme_id'
        );
    }
}
