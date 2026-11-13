<?php

namespace App\Actions\Config;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Config\ReferenceEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SC-01, BR-SC-03 — menonaktifkan data acuan: jalan keluar agar larangan menghapus tidak jadi
 * jalan buntu, sekaligus cara yang benar saat kategori berubah makna. Buat yang baru, nonaktifkan
 * yang lama — laporan lama tetap menyebut nama lamanya, pilihan baru tidak lagi menawarkannya.
 *
 * @method static Model run(ReferenceEntity<covariant Model> $entity, Model $record, bool $active)
 */
class ToggleReferenceEntity
{
    use AsAction;

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     *
     * @throws ValidationException
     */
    public function handle(ReferenceEntity $entity, Model $record, bool $active): Model
    {
        if (!$entity->deactivatable) {
            throw ValidationException::withMessages([
                'is_active' => "\"{$entity->label}\" tidak mengenal status aktif, tabelnya tidak menyimpannya.",
            ]);
        }

        $sebelumnya = (bool) $record->getAttribute('is_active');
        $record->update(['is_active' => $active]);
        $tercatat = $record->refresh();

        AuditTrail::record(
            $active ? "{$entity->label} diaktifkan" : "{$entity->label} dinonaktifkan",
            $tercatat,
            null,
            new AuditChange(
                old: ['is_active' => $sebelumnya],
                new: ['is_active' => $active],
            ),
        );

        return $tercatat;
    }
}
