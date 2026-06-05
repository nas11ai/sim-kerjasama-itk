<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StudyProgram> $studyPrograms
 */
class Faculty extends Model
{
    protected $fillable = ['name'];

    /** @return HasMany<StudyProgram, $this> */
    public function studyPrograms(): HasMany
    {
        return $this->hasMany(StudyProgram::class);
    }
}
