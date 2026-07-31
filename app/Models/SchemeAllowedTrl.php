<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemeAllowedTrl extends Model
{
    protected $table = 'scheme_allowed_trls';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $fillable = [
        'scheme_id',
        'trl_id',
    ];

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function technologyReadinessLevel(): BelongsTo
    {
        return $this->belongsTo(
            TechnologyReadinessLevel::class,
            'trl_id'
        );
    }
}
