<?php

namespace App\Actions\Config;

use App\Actions\Concerns\ChangesNoState;
use App\Support\Config\ReferenceEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * BR-SC-01 — berapa banyak yang masih memakai satu data acuan. Dipisah dari penghapusannya
 * karena layar memakai angka ini **sebelum** tombol ditekan: penolakan yang baru muncul
 * sesudahnya terbaca sebagai sistem rusak. **Tidak menulis audit (BR-RPT-09).** Kueri murni.
 *
 * @method static int run(ReferenceEntity<covariant Model> $entity, Model $record)
 */
class CountReferenceEntityUsage implements ChangesNoState
{
    use AsAction;

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     */
    public function handle(ReferenceEntity $entity, Model $record): int
    {
        $jumlah = 0;

        foreach ([$entity->usageRelation, ...$entity->additionalUsageRelations] as $nama) {
            $relation = $record->{$nama}();

            if (!$relation instanceof Relation) {
                throw new RuntimeException(
                    "Relasi \"{$nama}\" pada {$entity->model} bukan relasi Eloquent — "
                    .'cakupan BR-SC-01 tidak bisa dihitung.'
                );
            }

            $jumlah += $relation->count();
        }

        return $jumlah;
    }
}
