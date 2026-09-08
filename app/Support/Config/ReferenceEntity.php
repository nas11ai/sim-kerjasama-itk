<?php

namespace App\Support\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu jenis data acuan yang dikelola dari layar Konfigurasi Umum.
 *
 * @template TModel of Model
 */
final class ReferenceEntity
{
    /**
     * @param  class-string<TModel>  $model
     * @param  string  $usageRelation  relasi yang menjawab "masih dipakai siapa" (BR-SC-01)
     * @param  bool  $editable  false untuk acuan yang hanya boleh diubah lewat seeder (BR-SC-02)
     * @param  bool  $deactivatable  false untuk acuan yang tabelnya memang tidak punya `is_active`
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $model,
        public readonly string $usageRelation,
        public readonly string $usedBy,
        public readonly bool $editable = true,
        public readonly bool $deactivatable = true,
    ) {}
}
