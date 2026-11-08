<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// W154 — format berkas ekspor tabular (`ExportFormat`); baris lama dibuat sebagai CSV,
// tetapi kolom bawaan xlsx karena itulah format bawaan permintaan baru.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            $table->string('format', 8)->default('xlsx')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
