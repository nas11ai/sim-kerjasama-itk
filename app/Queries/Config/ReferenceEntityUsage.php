<?php

namespace App\Queries\Config;

use App\Actions\Concerns\ChangesNoState;
use App\Support\Config\ReferenceEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use RuntimeException;
use UnexpectedValueException;

/**
 * BR-SC-01 — berapa yang masih memakai data acuan. Satu `withCount` untuk layar (sebelum
 * Hapus ditekan) dan untuk DeleteReferenceEntity, supaya angkanya tidak pernah berbeda (W115).
 */
final class ReferenceEntityUsage implements ChangesNoState
{
    /** @param  ReferenceEntity<covariant Model>  $entity */
    public static function of(ReferenceEntity $entity, Model $record): int
    {
        $counted = self::counted($entity)->whereKey($record->getKey())->firstOrFail();

        return self::total($entity, $counted);
    }

    /**
     * Semua baris kategori itu, urut nama, beserta jumlah pemakainya.
     *
     * @param  ReferenceEntity<covariant Model>  $entity
     * @return list<array{record: Model, usageCount: int}>
     */
    public static function forAll(ReferenceEntity $entity): array
    {
        return array_values(self::counted($entity)->orderBy('name')->get()
            ->map(fn (Model $record): array => ['record' => $record, 'usageCount' => self::total($entity, $record)])
            ->all());
    }

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     * @return Builder<Model>
     */
    private static function counted(ReferenceEntity $entity): Builder
    {
        $model = $entity->model;
        $instance = new $model;

        foreach ($entity->usageRelations() as $name) {
            if (!$instance->{$name}() instanceof Relation) {
                throw new RuntimeException(
                    "Relasi \"{$name}\" pada {$entity->model} bukan relasi Eloquent — "
                    .'cakupan BR-SC-01 tidak bisa dihitung.'
                );
            }
        }

        return $model::query()->withCount($entity->usageRelations());
    }

    /** @param  ReferenceEntity<covariant Model>  $entity */
    private static function total(ReferenceEntity $entity, Model $counted): int
    {
        return array_sum(array_map(
            fn (string $name): int => self::countOf($counted, Str::snake($name).'_count'),
            $entity->usageRelations(),
        ));
    }

    /** Hitungan yang tak terbaca dilempar, bukan dianggap nol: nol berarti boleh dihapus. */
    private static function countOf(Model $counted, string $column): int
    {
        $count = $counted->getAttribute($column);

        if (is_int($count)) {
            return $count;
        }

        if (is_string($count) && ctype_digit($count)) {
            return intval($count);
        }

        throw new UnexpectedValueException("Kolom {$column} tidak memuat hitungan pemakai.");
    }
}
