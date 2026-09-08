<?php

namespace App\Actions\Config;

use App\Support\Audit\AuditTrail;
use App\Support\Config\ReferenceEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SC-01, BR-SC-03 — menonaktifkan data acuan.
 *
 * Ini jalan keluar yang membuat larangan menghapus (BR-SC-01) tidak menjadi
 * jalan buntu, sekaligus jalan yang benar saat sebuah kategori berubah makna:
 * buat yang baru, nonaktifkan yang lama. Laporan lama tetap menyebut nama
 * lamanya karena ia memang masih menunjuk baris lama, sementara pilihan baru
 * tidak lagi menawarkannya.
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
                'is_active' => "\"{$entity->label}\" tidak mengenal status aktif — tabelnya tidak menyimpannya.",
            ]);
        }

        $sebelumnya = (bool) $record->getAttribute('is_active');
        $record->update(['is_active' => $active]);
        $tercatat = $record->refresh();

        AuditTrail::record(
            $active ? "{$entity->label} diaktifkan" : "{$entity->label} dinonaktifkan",
            $tercatat,
            null,
            ['is_active' => $sebelumnya],
            ['is_active' => $active],
        );

        return $tercatat;
    }
}
