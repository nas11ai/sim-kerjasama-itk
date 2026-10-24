<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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

    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    /**
     * Alias for parent() so study-program orgs serialize as study_program.faculty for Inertia.
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    /**
     * Faculty → study_program tree shaped like the legacy Faculty::with('studyPrograms') payload.
     *
     * @return Collection<int, array{id: int, name: string, study_programs: array<int, array{id: int, name: string, faculty_id: int}>}>
     */
    public static function facultyOptions(): Collection
    {
        return static::query()
            ->where('type', 'faculty')
            ->orderBy('name')
            ->with(['children' => static function ($query): void {
                $query->where('type', 'study_program')->orderBy('name');
            }])
            ->get()
            ->map(static function (Organization $faculty): array {
                /** @var \Illuminate\Database\Eloquent\Collection<int, Organization> $children */
                $children = $faculty->children;

                return [
                    'id' => $faculty->id,
                    'name' => $faculty->name,
                    'study_programs' => $children
                        ->map(static fn (Organization $studyProgram): array => [
                            'id' => $studyProgram->id,
                            'name' => $studyProgram->name,
                            'faculty_id' => $faculty->id,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();
    }

    /**
     * @return list<int>
     */
    public static function subtreeIds(int $orgId): array
    {
        /** @var list<int> $ids */
        $ids = Cache::remember("org_subtree_{$orgId}", now()->addMinutes(30), function () use ($orgId) {
            $result = DB::select('
                WITH RECURSIVE org_subtree AS (
                    SELECT id FROM organizations WHERE id = ?
                    UNION ALL
                    SELECT o.id FROM organizations o
                    INNER JOIN org_subtree ot ON o.parent_id = ot.id
                    WHERE o.is_active = true
                )
                SELECT id FROM org_subtree
            ', [$orgId]);

            return array_map(
                static fn ($id): int => (int) $id,
                array_column($result, 'id'),
            );
        });

        return $ids;
    }

    /**
     * Leluhur organisasi berikut dirinya sendiri (ancestor-or-self), ditelusuri naik.
     *
     * BR-FE-02 — Form terlihat oleh organisasi tempat FormAccessControl dipasang DAN
     * semua unit di bawahnya, jadi yang dicocokkan adalah leluhur organisasi user.
     * Arahnya sengaja berlawanan dengan subtreeIds(); lihat ADR 0013.
     *
     * @return list<int>
     */
    public static function ancestorIds(int $orgId): array
    {
        /** @var list<int> $ids */
        $ids = Cache::remember("org_ancestors_{$orgId}", now()->addMinutes(30), function () use ($orgId) {
            $result = DB::select('
                WITH RECURSIVE org_ancestors AS (
                    SELECT id, parent_id FROM organizations WHERE id = ?
                    UNION ALL
                    SELECT o.id, o.parent_id FROM organizations o
                    INNER JOIN org_ancestors oa ON o.id = oa.parent_id
                    WHERE o.is_active = true
                )
                SELECT id FROM org_ancestors
            ', [$orgId]);

            return array_map(
                static fn ($id): int => (int) $id,
                array_column($result, 'id'),
            );
        });

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
        $cacheIds = [$organization->id];

        $parentId = $organization->parent_id;
        while ($parentId !== null) {
            $cacheIds[] = (int) $parentId;
            $parentId = self::query()->whereKey($parentId)->value('parent_id');
        }

        $originalParentId = $organization->getOriginal('parent_id');
        if ($organization->wasChanged('parent_id') && $originalParentId !== null) {
            $parentId = $originalParentId;
            while ($parentId !== null) {
                $cacheIds[] = (int) $parentId;
                $parentId = self::query()->whereKey($parentId)->value('parent_id');
            }
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
        $result = DB::select('
            WITH RECURSIVE org_descendants AS (
                SELECT id FROM organizations WHERE id = ?
                UNION ALL
                SELECT o.id FROM organizations o
                INNER JOIN org_descendants od ON o.parent_id = od.id
            )
            SELECT id FROM org_descendants
        ', [$orgId]);

        return array_map(
            static fn ($id): int => (int) $id,
            array_column($result, 'id'),
        );
    }
}
