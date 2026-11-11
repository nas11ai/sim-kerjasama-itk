<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR 0019 (contract R1) — langkah fase menyebut jenis + siklus; tanggalnya dibaca dari kalender
// periode pengajuan, jadi ketiga rujukan tanggal di langkah dihapus. Skema saja, tanpa migrasi
// data: aplikasi belum produksi, DB dev di-reset `migrate:fresh --seed` (pemilik 2026-11-10).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submission_date_id');
            $table->dropConstrainedForeignId('evaluation_date_id');
            $table->dropConstrainedForeignId('revision_date_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Migrasi ini tidak bisa dibalik: rujukan tanggal per langkah sudah dilebur ke kalender periode.');
    }
};
