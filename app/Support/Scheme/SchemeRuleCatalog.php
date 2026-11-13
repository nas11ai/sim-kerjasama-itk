<?php

namespace App\Support\Scheme;

/**
 * BR-SCH-06 — kunci `schemes.rules` yang bisa disunting dari layar (W107). Kunci baru =
 * entri di sini + pembacanya, tanpa migration. Pembacanya memeriksa `is_int`/list.
 */
final class SchemeRuleCatalog
{
    public const INTEGER_KEYS = [
        'min_reviewer_count' => 'Minimal Penilai',
        'max_reviewer_workload' => 'Beban Maks. Penilai',
        'max_student_members' => 'Anggota Mahasiswa Maks.',
    ];

    public const OUTPUT_TYPES_KEY = 'allowed_output_types';

    /** @return list<string> */
    public static function keys(): array
    {
        return [...array_keys(self::INTEGER_KEYS), self::OUTPUT_TYPES_KEY];
    }

    /** @return array<string, list<string>> aturan validasi berprefiks `rules.` */
    public static function validationRules(): array
    {
        $rules = [];

        foreach (array_keys(self::INTEGER_KEYS) as $key) {
            $rules["rules.{$key}"] = ['nullable', 'integer', 'min:1'];
        }

        $rules['rules.'.self::OUTPUT_TYPES_KEY] = ['nullable', 'array'];
        $rules['rules.'.self::OUTPUT_TYPES_KEY.'.*'] = ['string', 'exists:output_type_definitions,code'];

        return $rules;
    }

    /**
     * W108 — kunci katalog yang dikirim kosong dibuang, sisanya ditimpa; kunci di luar katalog
     * dari aturan tersimpan dipertahankan apa adanya.
     *
     * @template TStored
     *
     * @param  array<array-key, TStored>|null  $stored
     * @param  array<string, int|list<string>|null>  $submitted
     * @return array<array-key, TStored|int|list<string>>|null
     */
    public static function merge(?array $stored, array $submitted): ?array
    {
        $merged = $stored ?? [];

        foreach ($submitted as $key => $value) {
            unset($merged[$key]);

            if ($value !== null && $value !== []) {
                $merged[$key] = $value;
            }
        }

        return $merged === [] ? null : $merged;
    }
}
