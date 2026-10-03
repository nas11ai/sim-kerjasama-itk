<?php

namespace App\Actions\Config;

use App\Data\Request\ReferenceEntityInput;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Config\ReferenceEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SC-02, BR-SC-03 — membuat dan mengganti nama data acuan.
 *
 * Mengganti nama **tidak** menyentuh baris historis: tautannya tetap menunjuk
 * baris yang sama, jadi laporan lama ikut memakai nama baru. Itu memang arti
 * dari mengganti nama. Kalau kategorinya berubah makna, jalannya membuat
 * entity baru lalu menonaktifkan yang lama — bukan menulis ulang yang ini.
 *
 * Nilai lama dan barunya masuk jejak audit justru karena penggantian nama
 * mengubah tampilan seluruh laporan yang pernah memakainya. Tanpa jejak itu,
 * laporan yang "berubah sendiri" tidak punya penjelasan.
 *
 * @method static Model run(ReferenceEntity<covariant Model> $entity, ReferenceEntityInput $input, ?Model $record = null)
 */
class SaveReferenceEntity
{
    use AsAction;

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     *
     * @throws ValidationException
     */
    public function handle(ReferenceEntity $entity, ReferenceEntityInput $input, ?Model $record = null): Model
    {
        // BR-SC-02 — acuan read-only ditolak di Action, bukan hanya
        // disembunyikan di layar: layar bukan gerbang, dan jalur non-HTTP
        // tetap ada.
        if (!$entity->editable) {
            throw ValidationException::withMessages([
                'name' => "\"{$entity->label}\" hanya dapat diubah lewat seeder — nilainya mengikat "
                    .$entity->usedBy.' sekaligus.',
            ]);
        }

        $attributes = ['name' => $input->name];

        if ($record === null) {
            $model = $entity->model;

            // BR-SC-03 — kode diturunkan sekali dari namanya, lalu dibekukan.
            // Baris historis menautkan dirinya lewat kode, jadi kode yang ikut
            // berubah saat nama diganti akan memutus tautan itu diam-diam.
            if ($entity->codeColumn !== null) {
                // Nama kosong melahirkan kode kosong: kunci yang tidak menautkan apa pun,
                // dan kesalahannya baru terlihat saat laporan kehilangan barisnya.
                if (trim($input->name) === '') {
                    throw ValidationException::withMessages([
                        'name' => 'Nama harus diisi.',
                    ]);
                }

                $attributes[$entity->codeColumn] = Str::slug($input->name);
            }

            $saved = $model::create($attributes);

            AuditTrail::record("{$entity->label} ditambahkan", $saved, null, new AuditChange(new: $attributes));

            return $saved;
        }

        // Kode tidak ikut disunting (BR-SC-03): ReferenceEntityInput memang tidak membawanya.
        $before = $record->only(array_keys($attributes));
        $record->update($attributes);
        $saved = $record->refresh();

        AuditTrail::record(
            "{$entity->label} diperbarui",
            $saved,
            null,
            new AuditChange(
                old: $before,
                new: $attributes,
            ),
        );

        return $saved;
    }
}
