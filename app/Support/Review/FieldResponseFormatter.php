<?php

namespace App\Support\Review;

use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldResponse;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Database\Eloquent\Builder;

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
            'checkbox' => self::checkbox($field, $value),
            'radio', 'select' => self::optionLabel($field, $value),
            'number' => is_numeric($value) ? number_format((float) $value, 2) : $value,
            'date' => self::date($value),
            default => $value,
        };
    }

    private static function checkbox(ReviewFormField $field, string $value): string
    {
        $labels = array_map(
            static fn (string $one): string => self::optionLabel($field, $one),
            self::chosenValues($value),
        );

        return $labels === [] ? $value : implode(', ', $labels);
    }

    /**
     * Layar isi evaluasi menggabungkan pilihan checkbox dengan koma, jadi
     * pemisahnya dibuat sama persis dengan penulisnya di `ShowPage.vue`.
     *
     * @return list<string>
     */
    private static function chosenValues(string $value): array
    {
        $decoded = json_decode($value, true);
        $raw = is_array($decoded) ? $decoded : explode(',', $value);

        $chosen = [];

        foreach ($raw as $one) {
            $text = is_scalar($one) ? trim((string) $one) : '';

            if ($text !== '') {
                $chosen[] = $text;
            }
        }

        return $chosen;
    }

    private static function optionLabel(ReviewFormField $field, string $value): string
    {
        // Pembungkus closure-nya wajib: tanpa itu `OR` menyatu dengan penyaringan
        // per field, dan opsi milik field lain ikut terjaring.
        $option = $field->reviewFormFieldOptions()
            ->where(function (Builder $query) use ($value): void {
                $query->where('value', $value)->orWhere('label', $value);
            })
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
