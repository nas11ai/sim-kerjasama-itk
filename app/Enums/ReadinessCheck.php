<?php

namespace App\Enums;

/** BR-FE-36 — tiga cek kesiapan angkatan; urutan `cases()` = urutan tampil di halaman Kesiapan Angkatan. */
enum ReadinessCheck: string
{
    case MissingDate = 'missing_date';
    case DateOrder = 'date_order';
    case RequiredEvaluationForm = 'required_evaluation_form';

    public function title(): string
    {
        return match ($this) {
            self::MissingDate => 'Tanggal setiap langkah sudah diatur',
            self::DateOrder => 'Urutan tanggal masuk akal',
            self::RequiredEvaluationForm => 'Langkah yang direview punya Formulir Evaluasi wajib',
        };
    }
}
