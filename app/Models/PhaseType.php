<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
class PhaseType extends Model
{
    protected $fillable = ['name'];

    public function formPhaseDetails()
    {
        return $this->hasMany(FormPhaseDetail::class);
    }
}
