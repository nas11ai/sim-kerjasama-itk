<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property string $permission
 * @property-read Form|null $form
 * @property-read Organization|null $organization
 * @property-read Organization|null $studyProgram
 */
class FormAccessControl extends Model
{
    /**
     * Permissions that may be attached to a form access control.
     *
     * @var list<string>
     */
    public const ALLOWED_PERMISSIONS = [
        'submissions.create',
        'submissions.view-own',
        'submissions.view-all',
        'submissions.view-assigned',
        'reviewers.evaluate',
        'periods.manage',
    ];

    /**
     * @var array<string, string>
     */
    public const ROLE_TO_PERMISSION = [
        'researcher' => 'submissions.create',
        'Mahasiswa' => 'submissions.create',
        'reviewer_internal' => 'reviewers.evaluate',
        'reviewer_external' => 'reviewers.evaluate',
        'operator' => 'periods.manage',
        'Tenaga Kependidikan' => 'periods.manage',
        'admin' => 'submissions.view-all',
        'Admin' => 'submissions.view-all',
        'Super Admin' => 'submissions.view-all',
    ];

    protected $fillable = ['form_id', 'permission', 'organization_id'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
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

    public static function permissionForRoleName(string $roleName): ?string
    {
        return self::ROLE_TO_PERMISSION[$roleName] ?? null;
    }

    /**
     * Effective FAC permission strings for a user (Spatie permissions + legacy role mapping).
     *
     * @return list<string>
     */
    public static function effectivePermissionsFor(User $user): array
    {
        /** @var Collection<int, string> $permissions */
        $permissions = $user->getPermissionNames();

        foreach ($user->getRoleNames() as $roleName) {
            $mapped = self::permissionForRoleName((string) $roleName);
            if ($mapped !== null) {
                $permissions->push($mapped);
            }
        }

        return $permissions->unique()->values()->all();
    }

    /**
     * @param  Builder<FormAccessControl>  $query
     * @return Builder<FormAccessControl>
     */
    public function scopeAccessibleBy($query, User $user)
    {
        $permissions = self::effectivePermissionsFor($user);

        if ($permissions === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->getModel()->getTable().'.permission', $permissions);
    }
}
