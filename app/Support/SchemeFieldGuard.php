<?php

namespace App\Support;

use App\Models\FieldType;
use Illuminate\Validation\ValidationException;

/**
 * BR-SCH-05 — `trl_selector` tidak berdiri sendiri.
 *
 * Pilihan TRL disaring oleh skema yang dipilih peneliti; tanpa
 * `scheme_selector` di Form yang sama, tidak ada yang menentukan daftar TRL
 * mana yang sah, dan field itu akan menawarkan seluruh TRL yang ada atau tidak
 * sama sekali. Keduanya salah, dan keduanya baru ketahuan saat pengajuan
 * pertama dikirim.
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

        if ($hasTrl && !$hasScheme) {
            throw ValidationException::withMessages([
                'fields' => 'Field bertipe trl_selector membutuhkan scheme_selector di Form yang sama — '
                    .'pilihan TRL disaring oleh skema yang dipilih.',
            ]);
        }
    }
}
