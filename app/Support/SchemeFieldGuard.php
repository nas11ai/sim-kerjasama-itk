<?php

namespace App\Support;

use App\Models\FieldType;
use Illuminate\Validation\ValidationException;

/**
 * BR-SCH-05 — `trl_selector` tidak berdiri sendiri: pilihan TRL disaring skema
 * yang dipilih peneliti. Tanpa `scheme_selector` di Form yang sama, field itu
 * menawarkan seluruh TRL atau tidak satu pun — dua-duanya salah.
 */
final class SchemeFieldGuard
{
    public const SCHEME_SELECTOR = 'scheme_selector';

    public const TRL_SELECTOR = 'trl_selector';

    /**
     * @param  array<int, int|string>  $fieldTypeIds  seluruh field type yang akan dimiliki Form
     *
     * @throws ValidationException
     */
    public static function assertTrlHasSchemeSelector(array $fieldTypeIds): void
    {
        if ($fieldTypeIds === []) {
            return;
        }

        $names = FieldType::query()
            ->whereIn('id', array_map(static fn ($id): int => (int) $id, $fieldTypeIds))
            ->pluck('name')
            ->all();

        $hasTrl = in_array(self::TRL_SELECTOR, $names, true);
        $hasScheme = in_array(self::SCHEME_SELECTOR, $names, true);

        // Dijaga saat Form disusun, bukan saat diisi: dua-duanya baru ketahuan
        // saat pengajuan pertama dikirim.
        if ($hasTrl && !$hasScheme) {
            throw ValidationException::withMessages([
                'fields' => 'Isian bertipe trl_selector membutuhkan scheme_selector di Formulir yang sama: '
                    .'pilihan TKT disaring oleh skema yang dipilih.',
            ]);
        }
    }
}
