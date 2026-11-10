<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BR-FE-07 (ADR 0019) — langkah fase menyebut jenis tenggat + siklus; tanggalnya dibaca dari
// kalender periode pengajuan (BR-FE-26), jadi pengajuan yang menunjuk langkah wajib berperiode.
// Skema saja, tanpa backfill: aplikasi belum produksi, DB dev di-reset `migrate:fresh --seed`
// (pemilik 2026-11-10). `submission_date_id` menjadi nullable sampai kolomnya dihapus (R1 Task 8).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->string('deadline_type')->after('needs_review');
            $table->unsignedSmallInteger('deadline_cycle')->nullable()->after('deadline_type');
            $table->foreignId('submission_date_id')->nullable()->change();
        });

        DB::statement('ALTER TABLE form_submissions ADD CONSTRAINT form_submissions_detail_has_period '
            .'CHECK (form_phase_detail_id IS NULL OR submission_period_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE form_submissions DROP CONSTRAINT IF EXISTS form_submissions_detail_has_period');

        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->dropColumn(['deadline_type', 'deadline_cycle']);
            $table->foreignId('submission_date_id')->nullable(false)->change();
        });
    }
};
