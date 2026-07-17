<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

/**
 * @property-read Form|null $form
 * @property-read Role|null $role
 * @property-read Organization|null $organization
 * @property-read Organization|null $studyProgram
 */
class FormAccessControl extends Model
{
    protected $fillable = ['form_id', 'role_id', 'organization_id'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Legacy relation name for Inertia pages that still expect study_program / study_program.faculty.
     */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function formPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class);
    }
}
