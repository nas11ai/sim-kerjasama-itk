<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR 0019 — satu fase tertaut sekali per periode. Tautan ganda membuat celah jadwal fase itu
// terhitung dua kali di kartu fase. Skema saja, tanpa migrasi data: aplikasi belum produksi,
// DB dev di-reset `migrate:fresh --seed` (pemilik 2026-11-10).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_period_phases', function (Blueprint $table) {
            $table->unique(['submission_period_id', 'form_phase_id']);
        });
    }

    public function down(): void
    {
        Schema::table('submission_period_phases', function (Blueprint $table) {
            $table->dropUnique(['submission_period_id', 'form_phase_id']);
        });
    }
};
