<?php

namespace App\Support\Review;

use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldResponse;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Nilai jawaban evaluasi dalam bentuk yang terbaca manusia. Keluarannya teks
 * polos: ia dipakai layar Inertia sekaligus ringkasan teks, jadi tag HTML di
 * sini muncul sebagai sampah di salah satu dari keduanya.
 */
final class FieldResponseFormatter
{
    public static function readable(ReviewFormFieldResponse $response): string
    {
        if (!$response->hasValue()) {
            return '';
        }

        $value = $response->value ?? '';
        $field = $response->reviewFormField;

        // Tanpa field induk tidak ada tipe yang bisa dipakai memformat.
        if ($field === null) {
            return $value;
        }

        return match ($field->fieldType->name ?? '') {
            'checkbox' => self::checkbox($value),
            'radio', 'select' => self::optionLabel($field, $value),
            'number' => is_numeric($value) ? number_format((float) $value, 2) : $value,
            'date' => self::date($value),
            default => $value,
        };
    }

    private static function checkbox(string $value): string
    {
        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return $value;
        }

        // Pilihan checkbox disimpan sebagai array string. Isi lain bukan
        // pilihan yang bisa dibaca, jadi nilai mentahnya yang ditampilkan.
        $labels = array_filter($decoded, 'is_string');

        return count($labels) === count($decoded) ? implode(', ', $labels) : $value;
    }

    private static function optionLabel(ReviewFormField $field, string $value): string
    {
        $option = $field->reviewFormFieldOptions()
            ->where('value', $value)
            ->orWhere('label', $value)
            ->first();

        if ($option === null) {
            return $value;
        }

        return $option->label;
    }

    private static function date(string $value): string
    {
        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (InvalidFormatException) {
            return $value;
        }
    }
}
