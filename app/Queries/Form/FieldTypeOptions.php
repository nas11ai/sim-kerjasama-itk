<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FieldType;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pilihan tipe field di editor Form. `hasOptions` memberi tahu editor kapan opsi diminta dan
 * `limitType` batas mana yang ditawarkan (W103), supaya daftar tipe hanya hidup di FieldType. `name`
 * dipakai Pratinjau Form Evaluasi untuk merender field seperti layar reviewer (ReviewerEvaluation).
 *
 * @phpstan-type FieldTypeOption array{value: string, name: string, label: string, hasOptions: bool, limitType: 'length'|'value'|null}
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
        'submission_title' => 'Judul pengajuan',
    ];

    /** Nama tipe yang tak dikenal tampil apa adanya. */
    public static function label(string $name): string
    {
        return self::LABELS[$name] ?? $name;
    }

    /** @return list<FieldTypeOption> */
    public function handle(): array
    {
        return array_values(FieldType::query()->orderBy('id')->get(['id', 'name'])
            ->map(fn (FieldType $type): array => [
                'value' => (string) $type->id,
                'name' => strtolower($type->name),
                'label' => self::label($type->name),
                'hasOptions' => in_array($type->name, FieldType::OPTION_TYPES, true),
                'limitType' => self::limitType($type->name),
            ])
            ->all());
    }

    /** @return 'length'|'value'|null */
    private static function limitType(string $name): ?string
    {
        return match (true) {
            in_array($name, FieldType::LENGTH_LIMIT_TYPES, true) => 'length',
            in_array($name, FieldType::VALUE_LIMIT_TYPES, true) => 'value',
            default => null,
        };
    }
}
