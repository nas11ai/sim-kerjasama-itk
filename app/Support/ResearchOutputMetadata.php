<?php

namespace App\Support;

use App\Data\View\ResearchOutput\OutputFieldSpecData;
use App\Models\OutputTypeDefinition;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

/**
 * BR-RO-09, W140 — isi metadata luaran terhadap spesifikasi tipenya: wajib terisi, bentuk nilai
 * sesuai `type`, dan hanya kunci spesifikasi yang tersimpan. JSONB ini dibaca pelaporan LPPM,
 * jadi kunci karangan klien tidak boleh ikut masuk.
 */
final class ResearchOutputMetadata
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string|int|null> metadata bersih, siap disimpan
     *
     * @throws ValidationException
     */
    public static function validated(OutputTypeDefinition $definition, array $metadata): array
    {
        $errors = [];
        $clean = [];

        foreach ($definition->fieldSpecs() as $spec) {
            $value = self::scalarOf($metadata[$spec->key] ?? null);
            $message = self::problem($definition, $spec, $value);

            if ($message !== null) {
                $errors["metadata.{$spec->key}"] = $message;
            }

            $clean[$spec->key] = self::stored($spec, $value);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /** Teks dirapikan, kosong jadi null; selain teks dan bilangan bulat = `false` (bentuk ditolak). */
    private static function scalarOf(mixed $raw): string|int|false|null
    {
        if (is_string($raw)) {
            return trim($raw) === '' ? null : trim($raw);
        }

        return is_int($raw) || $raw === null ? $raw : false;
    }

    /** Angka disimpan sebagai bilangan supaya kueri JSONB pelaporan (`(metadata->>'year')::int`) tidak gagal. */
    private static function stored(OutputFieldSpecData $spec, string|int|false|null $value): string|int|null
    {
        if ($value === false) {
            return null;
        }

        if ($spec->type !== 'number' || !is_string($value)) {
            return $value;
        }

        $number = filter_var($value, FILTER_VALIDATE_INT);

        return $number === false ? $value : $number;
    }

    private static function problem(OutputTypeDefinition $definition, OutputFieldSpecData $spec, string|int|false|null $value): ?string
    {
        if ($value === null) {
            // BR-RO-03 — yang tidak wajib boleh kosong, termasuk end_date PKS berjalan.
            return $spec->required ? "Isian \"{$spec->label}\" wajib diisi untuk tipe {$definition->name}." : null;
        }

        return $value !== false && self::fits($spec, $value) ? null : "Isian \"{$spec->label}\" tidak valid.";
    }

    private static function fits(OutputFieldSpecData $spec, string|int $value): bool
    {
        if (is_int($value)) {
            return in_array($spec->type, ['number', 'text', 'textarea'], true);
        }

        return match ($spec->type) {
            'number' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'date' => self::isDate($value),
            'select' => in_array($value, $spec->optionValues(), true),
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value) === 1,
            default => true,
        };
    }

    private static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
