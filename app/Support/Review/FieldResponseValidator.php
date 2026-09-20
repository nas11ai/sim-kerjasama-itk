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
            return $field->is_required ? ["Field '{$field->label}' is required"] : [];
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
                ? ["Field '{$label}' must be a valid email address"] : [],
            'url' => filter_var($value, FILTER_VALIDATE_URL) === false
                ? ["Field '{$label}' must be a valid URL"] : [],
            'number' => is_numeric($value) ? [] : ["Field '{$label}' must be a number"],
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
                ? "Field '{$label}' must be at least {$limit} characters" : null,
            'max_length' => mb_strlen($value) > (int) $limit
                ? "Field '{$label}' must not exceed {$limit} characters" : null,
            'min_value' => is_numeric($value) && (float) $value < (float) $limit
                ? "Field '{$label}' must be at least {$limit}" : null,
            'max_value' => is_numeric($value) && (float) $value > (float) $limit
                ? "Field '{$label}' must not exceed {$limit}" : null,
            default => null,
        };
    }
}
