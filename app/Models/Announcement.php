<?php

namespace App\Models;

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

/**
 * @property-read User|null $announcementCreator
 * @property-read Collection<int, AnnouncementFile> $announcementFiles
 * @property-read Collection<int, Role> $targetRoles
 */
class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'type',
        'expired_at',
        'created_by',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function announcementCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementFile, $this>
     */
    public function announcementFiles(): HasMany
    {
        return $this->hasMany(AnnouncementFile::class);
    }

    public function announcementReader()
    {
        return $this->belongsToMany(User::class, 'announcement_users')
            ->withTimestamps();
    }

    /**
     * fe-v2-design §4.7 — kosong berarti semua pengguna login.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function targetRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'announcement_roles');
    }

    /** @return list<string> */
    public function targetRoleNames(): array
    {
        return array_values($this->targetRoles->map(static fn (Role $role): string => $role->name)->all());
    }

    /**
     * Menulis lewat Model, bukan Support: Support dilarang menulis state (SupportPurityTest).
     *
     * @param  list<string>  $roleNames
     */
    public function syncTargetRoles(array $roleNames): void
    {
        $roleIds = Role::query()
            ->where('guard_name', PermissionSeeder::GUARD)
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->all();

        $this->targetRoles()->sync($roleIds);
    }
}
