<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-RPT-02, BR-RPT-03, BR-RPT-08, BR-RPT-12 — riwayat permintaan ekspor.
 *
 * `completed_at` dan `file_deleted_at` sengaja dua kolom terpisah. Yang
 * pertama menentukan kapan berkasnya jatuh tempo (24 jam sesudahnya), yang
 * kedua mencatat bahwa ia benar-benar sudah dihapus — tanpa kolom kedua,
 * "sudah lewat 24 jam" dan "berkasnya sudah tidak ada" bercampur, padahal
 * keduanya menghasilkan penolakan yang berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64);
            $table->jsonb('filters')->nullable();
            $table->string('status', 32)->default('queued');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('file_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('file_deleted_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'created_at']);
            // Dipakai penjadwal retensi untuk menemukan yang jatuh tempo.
            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
