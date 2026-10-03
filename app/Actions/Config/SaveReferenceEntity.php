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
 * baris yang sama, jadi laporan lama ikut nama baru. Itu arti mengganti nama.
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
            return $this->create($entity, $input, $attributes);
        }

        // Kode tidak ikut disunting (BR-SC-03): ReferenceEntityInput memang tidak membawanya.
        // Kategori berubah makna: buat entity baru, nonaktifkan yang lama.
        $before = $record->only(array_keys($attributes));
        $record->update($attributes);
        $saved = $record->refresh();

        // Nilai lama dan baru masuk jejak audit: ganti nama mengubah
        // tampilan semua laporan yang pernah memakainya, dan tanpa jejak itu
        // laporan yang "berubah sendiri" tidak punya penjelasan.
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

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     * @param  array<string, string>  $attributes
     *
     * @throws ValidationException
     */
    private function create(ReferenceEntity $entity, ReferenceEntityInput $input, array $attributes): Model
    {
        $model = $entity->model;

        // BR-SC-03 — kode diturunkan sekali dari namanya, lalu dibekukan.
        // Baris historis menautkan dirinya lewat kode, jadi kode yang ikut
        // berubah saat nama diganti akan memutus tautan itu diam-diam.
        if ($entity->codeColumn !== null) {
            $attributes = $this->withCode($entity, $input, $attributes);
        }

        $saved = $model::create($attributes);

        AuditTrail::record("{$entity->label} ditambahkan", $saved, null, new AuditChange(new: $attributes));

        return $saved;
    }

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     * @param  array<string, string>  $attributes
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function withCode(ReferenceEntity $entity, ReferenceEntityInput $input, array $attributes): array
    {
        // Nama kosong melahirkan kode kosong: kunci yang tidak menautkan apa pun,
        // dan kesalahannya baru terlihat saat laporan kehilangan barisnya.
        if (trim($input->name) === '') {
            throw ValidationException::withMessages([
                'name' => 'Nama harus diisi.',
            ]);
        }

        $attributes[$entity->codeColumn] = Str::slug($input->name);

        return $attributes;
    }
}
