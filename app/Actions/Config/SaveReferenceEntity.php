<?php

namespace App\Actions\Config;

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
 * @method static Model run(ReferenceEntity<covariant Model> $entity, array<string, mixed> $attributes, ?Model $record = null)
 */
class SaveReferenceEntity
{
    use AsAction;

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function handle(ReferenceEntity $entity, array $attributes, ?Model $record = null): Model
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

        if ($record === null) {
            $model = $entity->model;

            // BR-SC-03 — kode diturunkan sekali dari namanya, lalu dibekukan.
            // Baris historis menautkan dirinya lewat kode, jadi kode yang ikut
            // berubah saat nama diganti akan memutus tautan itu diam-diam.
            if ($entity->codeColumn !== null) {
                $nama = $attributes['name'] ?? null;

                // Diperiksa, bukan di-cast: kode yang lahir dari nilai yang
                // bukan teks akan jadi kunci yang tidak menautkan apa pun, dan
                // kesalahannya baru terlihat jauh belakangan saat laporan
                // kehilangan barisnya.
                if (!is_string($nama) || trim($nama) === '') {
                    throw ValidationException::withMessages([
                        'name' => 'Nama harus diisi.',
                    ]);
                }

                $attributes[$entity->codeColumn] = Str::slug($nama);
            }

            $tercatat = $model::create($attributes);

            AuditTrail::record("{$entity->label} ditambahkan", $tercatat, null, null, $attributes);

            return $tercatat;
        }

        // Kode sengaja tidak ikut disunting; lihat alasannya di atas.
        unset($attributes[$entity->codeColumn ?? '']);

        $sebelumnya = $record->only(array_keys($attributes));
        $record->update($attributes);
        $tercatat = $record->refresh();

        AuditTrail::record("{$entity->label} diperbarui", $tercatat, null, $sebelumnya, $attributes);

        return $tercatat;
    }
}
