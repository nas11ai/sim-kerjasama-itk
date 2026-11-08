<?php

namespace App\Enums;

use Maatwebsite\Excel\Excel;

/**
 * W154 — format berkas ekspor tabular (frame OPERATOR-07-02 "Excel / CSV"). Excel bawaan.
 */
enum ExportFormat: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Xlsx => 'Excel',
            self::Csv => 'CSV',
        };
    }

    /** Writer type `maatwebsite/excel` untuk `Excel::store`. */
    public function writerType(): string
    {
        return match ($this) {
            self::Xlsx => Excel::XLSX,
            self::Csv => Excel::CSV,
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}
