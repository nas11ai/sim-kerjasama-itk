<?php

// tests/Feature/Migrations/AddFormatToExportJobsTest.php
//
// E2E-04 — sebelum W154 penulis ekspor hanya menghasilkan CSV. Baris lama harus terbaca
// `csv`, supaya "Ekspor Ulang" mengulang permintaan aslinya, bukan berganti ke Excel.

use App\Models\User;
use App\Support\Reporting\PrintableDocument;
use Illuminate\Support\Facades\DB;

function formatMigration(): object
{
    return require database_path('migrations/2026_11_09_000000_add_format_to_export_jobs.php');
}

function legacyExportJob(string $type): int
{
    return DB::table('export_jobs')->insertGetId([
        'requested_by' => User::factory()->create()->id,
        'type' => $type,
        'status' => 'completed',
        'row_count' => 3,
        'attempts' => 1,
        'file_path' => "exports/{$type}-1-20261101-090000.csv",
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('[E2E-04] baris ekspor tabular lama terbaca csv, baris cetak dan baris baru tetap xlsx', function () {
    formatMigration()->down();
    $rekap = legacyExportJob('submissions');
    $luaran = legacyExportJob('research-outputs');
    $cetak = legacyExportJob(PrintableDocument::ACCEPTANCE_LETTER);

    formatMigration()->up();
    $baru = legacyExportJob('submissions');

    expect(DB::table('export_jobs')->whereIn('id', [$rekap, $luaran])->pluck('format')->all())->toBe(['csv', 'csv'])
        ->and(DB::table('export_jobs')->where('id', $cetak)->value('format'))->toBe('xlsx')
        ->and(DB::table('export_jobs')->where('id', $baru)->value('format'))->toBe('xlsx');
});
