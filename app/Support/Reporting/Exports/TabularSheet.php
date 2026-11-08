<?php

namespace App\Support\Reporting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * W154 — satu lembar tabular untuk `maatwebsite/excel`; xlsx dan csv ditulis dari sini,
 * jadi kedua format selalu berisi kolom dan baris yang sama.
 */
final class TabularSheet implements FromArray, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|null>>  $rows
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    /** @return list<list<string|int|null>> */
    public function array(): array
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->headings;
    }
}
