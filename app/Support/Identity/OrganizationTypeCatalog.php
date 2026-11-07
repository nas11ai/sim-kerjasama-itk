<?php

namespace App\Support\Identity;

/**
 * Tipe unit organisasi. Kosakata mesin, bukan data acuan: `faculty`/`study_program` dibaca
 * registrasi (RegistrableStudyPrograms) dan laporan (W112).
 */
final class OrganizationTypeCatalog
{
    public const LABELS = [
        'institution' => 'Institusi',
        'faculty' => 'Fakultas',
        'study_program' => 'Program Studi',
        'unit' => 'Unit Kerja',
    ];

    /** Tipe di luar katalog (data lama) tampil apa adanya. */
    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        $options = [];

        foreach (self::LABELS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
