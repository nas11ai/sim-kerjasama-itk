<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W141 — berkas bukti luaran kini unggahan sungguhan di disk privat. Nama asli dan ukurannya
 * disimpan supaya layar menampilkan "Artikel-Full.pdf · 1,1 MB", bukan nama acak Laravel.
 * Nullable untuk baris lama yang hanya punya `file_path`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_output_files', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('file_url');
            $table->unsignedBigInteger('size')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('research_output_files', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'size']);
        });
    }
};
