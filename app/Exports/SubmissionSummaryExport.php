<?php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SubmissionSummaryExport implements FromCollection, WithHeadings
{
    public function collection() { return collect([]); } // placeholder
    public function headings(): array
    {
        return ['ID', 'Judul', 'Status', 'Scheme', 'Lead Researcher', 'Tanggal Submit'];
    }
}
