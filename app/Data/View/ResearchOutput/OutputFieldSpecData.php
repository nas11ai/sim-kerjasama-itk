<?php

namespace App\Data\View\ResearchOutput;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * BR-RO-09, W140 — satu field metadata tipe luaran, dari `output_type_definitions.fields`.
 * Layar merender kontrolnya dari sini; Guard menegakkan `required` dan bentuk `type`.
 */
final class OutputFieldSpecData extends Data
{
    public const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'url'];

    /** @param  list<array{value: string, label: string}>  $options */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required,
        public array $options,
        public ?string $hint,
        public ?string $emptyLabel,
    ) {}

    /**
     * Satu entri `fields`; entri tanpa `key` = data belum lengkap → null. Tanpa `label`, kuncinya
     * yang tampil; `type` di luar daftar dibaca teks.
     *
     * @param  array{key?: string, label?: string, type?: string, required?: bool, options?: list<array{value: string, label: string}>, hint?: string, empty_label?: string}  $field
     */
    public static function fromEntry(array $field): ?self
    {
        if (!isset($field['key'])) {
            return null;
        }

        $field += ['label' => $field['key'], 'type' => 'text', 'required' => false, 'options' => [], 'hint' => null, 'empty_label' => null];

        return new self(
            key: $field['key'],
            label: $field['label'],
            type: in_array($field['type'], self::TYPES, true) ? $field['type'] : 'text',
            required: $field['required'] === true,
            options: $field['options'],
            hint: $field['hint'],
            emptyLabel: $field['empty_label'],
        );
    }

    /** @return list<string> */
    public function optionValues(): array
    {
        return array_column($this->options, 'value');
    }

    /** Nilai tampilan satu isian: label opsi untuk select, `d M Y` untuk tanggal, selain itu apa adanya. */
    public function displayValue(string|int $value): string
    {
        $text = (string) $value;

        return match ($this->type) {
            'select' => $this->optionLabel($text) ?? $text,
            'date' => CarbonImmutable::parse($text)->settings(['locale' => 'id'])->translatedFormat('d M Y'),
            default => $text,
        };
    }

    public function optionLabel(string $value): ?string
    {
        foreach ($this->options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return null;
    }
}
