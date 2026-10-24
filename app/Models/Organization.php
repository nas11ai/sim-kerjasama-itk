<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property int|null $parent_id
 * @property bool $is_active
 */
class Organization extends Model
{
    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'json',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    /** @return HasMany<Organization, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    /**
     * Alias for parent() so study-program orgs serialize as study_program.faculty for Inertia.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    /**
     * @return list<int>
     */
    public static function subtreeIds(int $orgId): array
    {
        /** @var list<int> $ids */
        $ids = Cache::remember("org_subtree_{$orgId}", now()->addMinutes(30), fn () => self::idColumn(DB::select('
            WITH RECURSIVE org_subtree AS (
                SELECT id FROM organizations WHERE id = ?
                UNION ALL
                SELECT o.id FROM organizations o
                INNER JOIN org_subtree ot ON o.parent_id = ot.id
                WHERE o.is_active = true
            )
            SELECT id FROM org_subtree
        ', [$orgId])));

        return $ids;
    }

    /**
     * BR-FE-02 — Form terlihat di organisasi tempat FormAccessControl dipasang DAN semua unit di
     * bawahnya, jadi yang dicocokkan leluhur organisasi user berikut dirinya (ancestor-or-self).
     * Arahnya sengaja NAIK, kebalikan subtreeIds(); lihat ADR 0013.
     *
     * @return list<int>
     */
    public static function ancestorIds(int $orgId): array
    {
        /** @var list<int> $ids */
        $ids = Cache::remember("org_ancestors_{$orgId}", now()->addMinutes(30), fn () => self::idColumn(DB::select('
            WITH RECURSIVE org_ancestors AS (
                SELECT id, parent_id FROM organizations WHERE id = ?
                UNION ALL
                SELECT o.id, o.parent_id FROM organizations o
                INNER JOIN org_ancestors oa ON o.id = oa.parent_id
                WHERE o.is_active = true
            )
            SELECT id FROM org_ancestors
        ', [$orgId])));

        return $ids;
    }

    protected static function booted(): void
    {
        static::saved(function (Organization $organization): void {
            self::forgetSubtreeCachesFor($organization);
            self::forgetAncestorCachesFor($organization);
        });

        static::deleted(function (Organization $organization): void {
            self::forgetSubtreeCachesFor($organization);
            self::forgetAncestorCachesFor($organization);
        });
    }

    private static function forgetSubtreeCachesFor(Organization $organization): void
    {
        $cacheIds = [$organization->id, ...self::parentChain($organization->parent_id)];

        $originalParentId = $organization->getOriginal('parent_id');
        if ($organization->wasChanged('parent_id') && $originalParentId !== null) {
            $cacheIds = [...$cacheIds, ...self::parentChain((int) $originalParentId)];
        }

        foreach (array_unique($cacheIds) as $id) {
            Cache::forget("org_subtree_{$id}");
        }
    }

    /**
     * Rantai leluhur sebuah node berubah kalau node itu sendiri berpindah induk atau
     * dinonaktifkan — dan perubahan itu ikut menggeser rantai SELURUH keturunannya.
     * Karena itu invalidasinya menelusuri turun, kebalikan dari forgetSubtreeCachesFor().
     */
    private static function forgetAncestorCachesFor(Organization $organization): void
    {
        foreach (self::descendantIdsIncludingInactive($organization->id) as $id) {
            Cache::forget("org_ancestors_{$id}");
        }
    }

    /**
     * Sengaja TIDAK memfilter is_active: node yang baru saja dinonaktifkan justru
     * yang cache-nya paling perlu dibuang.
     *
     * @return list<int>
     */
    private static function descendantIdsIncludingInactive(int $orgId): array
    {
        return self::idColumn(DB::select('
            WITH RECURSIVE org_descendants AS (
                SELECT id FROM organizations WHERE id = ?
                UNION ALL
                SELECT o.id FROM organizations o
                INNER JOIN org_descendants od ON o.parent_id = od.id
            )
            SELECT id FROM org_descendants
        ', [$orgId]));
    }

    /** @return list<int> */
    private static function parentChain(?int $parentId): array
    {
        $chain = [];
        while ($parentId !== null) {
            $chain[] = $parentId;
            $next = self::query()->whereKey($parentId)->value('parent_id');
            $parentId = $next === null ? null : (int) $next;
        }

        return $chain;
    }

    /**
     * @param  array<int, object>  $rows
     * @return list<int>
     */
    private static function idColumn(array $rows): array
    {
        return array_map(static fn ($id): int => (int) $id, array_column($rows, 'id'));
    }
}
