<?php

namespace App\Support\Review;

use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldResponse;

/**
 * Galat validasi satu jawaban evaluasi. `final class` statis mengikuti preseden
 * `RequiredFieldGuard`: sebuah guard bukan use case, jadi ia tidak berhak jadi
 * Action.
 */
final class FieldResponseValidator
{
    /** @return array<int, string> */
    public static function errorsFor(ReviewFormFieldResponse $response): array
    {
        $field = $response->reviewFormField;

        // Jawaban tanpa field induk tidak punya aturan yang bisa dilanggar.
        if ($field === null) {
            return [];
        }

        // Nilai kosong hanya bisa melanggar satu hal. Aturan tipe dan aturan
        // kustom tidak punya apa pun untuk diperiksa.
        if (!$response->hasValue()) {
            return $field->is_required ? ["Isian '{$field->label}' wajib diisi."] : [];
        }

        $value = $response->value ?? '';

        return [
            ...self::typeErrors($field, $value),
            ...self::customRuleErrors($field, $value),
        ];
    }

    /** @return array<int, string> */
    private static function typeErrors(ReviewFormField $field, string $value): array
    {
        $label = $field->label;

        return match ($field->fieldType->name ?? '') {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? ["Isian '{$label}' harus berupa alamat email yang valid."] : [],
            'url' => filter_var($value, FILTER_VALIDATE_URL) === false
                ? ["Isian '{$label}' harus berupa URL yang valid."] : [],
            'number' => is_numeric($value) ? [] : ["Isian '{$label}' harus berupa angka."],
            default => [],
        };
    }

    /** @return array<int, string> */
    private static function customRuleErrors(ReviewFormField $field, string $value): array
    {
        if (!$field->hasValidationRules()) {
            return [];
        }

        $errors = [];

        foreach ($field->validation_rules ?? [] as $rule => $limit) {
            // Kolomnya JSON dan jalur tulisnya cuma `nullable|array`, jadi nilai
            // yang tidak bisa dibandingkan dilewati, bukan meledak.
            if (!is_int($limit) && !is_float($limit) && !is_string($limit)) {
                continue;
            }

            $error = self::ruleError($rule, $limit, $value, $field->label);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private static function ruleError(string $rule, int|float|string $limit, string $value, string $label): ?string
    {
        return match ($rule) {
            'min_length' => mb_strlen($value) < (int) $limit
                ? "Isian '{$label}' minimal {$limit} karakter." : null,
            'max_length' => mb_strlen($value) > (int) $limit
                ? "Isian '{$label}' maksimal {$limit} karakter." : null,
            'min_value' => is_numeric($value) && (float) $value < (float) $limit
                ? "Isian '{$label}' minimal {$limit}." : null,
            'max_value' => is_numeric($value) && (float) $value > (float) $limit
                ? "Isian '{$label}' maksimal {$limit}." : null,
            default => null,
        };
    }
}
