<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Isi tab General & Fields. `affectedDrafts` adalah pemilik draft berjalan (W85), angka
 * yang sama dengan penerima notifikasi, terlihat sebelum Admin menyimpan (BR-FE-13).
 */
final class FormGeneralData extends Data
{
    /**
     * @param  list<FormFieldEditData>  $fields
     * @param  list<ArchivedFieldData>  $archivedFields
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?int $formTypeId,
        public bool $isActive,
        public array $fields,
        public array $archivedFields,
        public int $affectedDrafts,
    ) {}
}
