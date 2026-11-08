<?php

use App\Support\Reporting\PrintableDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// W154 — format berkas ekspor tabular (`ExportFormat`), bawaan xlsx untuk permintaan baru.
// E2E-04 — sebelum W154 penulisnya hanya menghasilkan CSV, jadi baris tabular lama ditandai
// csv supaya "Ekspor Ulang" mengulang permintaan aslinya. Baris cetak PDF tidak membacanya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            $table->string('format', 8)->default('xlsx')->after('type');
        });

        DB::table('export_jobs')->whereNotIn('type', PrintableDocument::all())->update(['format' => 'csv']);
    }

    public function down(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
