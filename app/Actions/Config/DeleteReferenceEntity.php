<?php

namespace App\Actions\Config;

use App\Queries\Config\ReferenceEntityUsage;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Config\ReferenceEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SC-01 — data acuan yang masih dipakai relasi aktif tidak bisa dihapus.
 *
 * Penolakannya menyebut **berapa** yang memakainya: angka itu yang memberi
 * tahu Admin apakah ia salah memilih baris atau harus menonaktifkan saja.
 *
 * @method static void run(ReferenceEntity<covariant Model> $entity, Model $record)
 */
class DeleteReferenceEntity
{
    use AsAction;

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     *
     * @throws ValidationException
     */
    public function handle(ReferenceEntity $entity, Model $record): void
    {
        $pemakai = ReferenceEntityUsage::of($entity, $record);

        if ($pemakai > 0) {
            throw ValidationException::withMessages([
                'id' => "\"{$entity->label}\" ini masih dipakai {$pemakai} {$entity->usedBy}. "
                    .'Nonaktifkan saja agar tidak lagi ditawarkan, tanpa menghapus jejak yang sudah memakainya.',
            ]);
        }

        // Audit ditulis sebelum baris hilang: setelahnya nama dan atributnya
        // tidak bisa dibaca lagi, dan entri "sesuatu dihapus" tanpa menyebut
        // apa tidak menolong siapa pun.
        $tercatat = $record;

        AuditTrail::record(
            "{$entity->label} dihapus",
            $tercatat,
            null,
            new AuditChange(
                old: ['name' => $record->getAttribute('name')],
            ),
        );

        $record->delete();
    }
}
