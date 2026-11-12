<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $form_id
 * @property string $permission
 * @property int $organization_id
 * @property int $phase_count dimuat `withCount` FormAccessRules (BR-FE-20)
 * @property-read Form|null $form
 * @property-read Organization|null $organization
 * @property-read Organization|null $studyProgram
 *
 * @method static Builder<static> accessibleBy(User $user)
 * @method static Builder<static> query()
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
     * Legacy role → permission map retained for data migration / admin display only.
     * Active authorization uses Spatie getAllPermissions().
     *
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

    /**
     * BR-FE-02 — satu gabungan form, permission, dan organisasi cukup tercatat sekali.
     */
    public static function grantExists(int $formId, string $permission, int $organizationId, ?int $exceptId = null): bool
    {
        return static::query()
            ->where(['form_id' => $formId, 'permission' => $permission, 'organization_id' => $organizationId])
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * BR-RPT-09 — bentuk yang sama untuk nilai lama dan baru di layar audit.
     *
     * @return array{form_id: int, permission: string, organization_id: int}
     */
    public function auditSnapshot(): array
    {
        return [
            'form_id' => $this->form_id,
            'permission' => $this->permission,
            'organization_id' => $this->organization_id,
        ];
    }

    public static function permissionForRoleName(string $roleName): ?string
    {
        return self::ROLE_TO_PERMISSION[$roleName] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function permissionNamesFor(User $user): array
    {
        /** @var Collection<int, string> $permissions */
        $permissions = $user->getAllPermissions()->pluck('name');

        return $permissions->unique()->values()->all();
    }

    /**
     * Organisasi yang boleh memasang FormAccessControl agar terlihat oleh $user:
     * organisasi user sendiri berikut seluruh leluhurnya.
     *
     * BR-FE-02 — arahnya anak-menjangkau-leluhur, bukan sebaliknya. Lihat ADR 0013.
     *
     * @return list<int>
     */
    public static function organizationAncestorsFor(User $user): array
    {
        $organizationId = $user->organization?->id;

        if ($organizationId === null) {
            return [];
        }

        return Organization::ancestorIds((int) $organizationId);
    }

    /**
     * @param  Builder<FormAccessControl>  $query
     * @return Builder<FormAccessControl>
     */
    public function scopeAccessibleBy($query, User $user)
    {
        $permissions = self::permissionNamesFor($user);
        $ancestors = self::organizationAncestorsFor($user);
        $table = $query->getModel()->getTable();

        if ($permissions === [] || $ancestors === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn($table.'.permission', $permissions)
            ->whereIn($table.'.organization_id', $ancestors);
    }
}
