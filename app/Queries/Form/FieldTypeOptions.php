<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FieldType;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pilihan tipe field di editor Form. `hasOptions` memberi tahu editor kapan opsi diminta,
 * supaya daftar tipe ber-opsi hanya hidup di FieldType::OPTION_TYPES.
 *
 * @phpstan-type FieldTypeOption array{value: string, label: string, hasOptions: bool}
 *
 * @method static list<FieldTypeOption> run()
 */
class FieldTypeOptions implements ChangesNoState
{
    use AsAction;

    private const LABELS = [
        'text' => 'Teks',
        'email' => 'Email',
        'number' => 'Angka',
        'textarea' => 'Teks panjang',
        'select' => 'Pilihan (dropdown)',
        'radio' => 'Pilihan tunggal',
        'checkbox' => 'Kotak centang',
        'date' => 'Tanggal',
        'time' => 'Waktu',
        'file' => 'Berkas',
        'url' => 'URL',
        'phone' => 'Telepon',
        'scheme_selector' => 'Pilihan skema',
        'trl_selector' => 'Pilihan TRL',
    ];

    /** @return list<FieldTypeOption> */
    public function handle(): array
    {
        return array_values(FieldType::query()->orderBy('id')->get(['id', 'name'])
            ->map(fn (FieldType $type): array => [
                'value' => (string) $type->id,
                'label' => self::LABELS[$type->name] ?? $type->name,
                'hasOptions' => in_array($type->name, FieldType::OPTION_TYPES, true),
            ])
            ->all());
    }
}
