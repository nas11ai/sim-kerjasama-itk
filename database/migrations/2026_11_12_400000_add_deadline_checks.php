<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// BR-FE-25 + BR-FE-07 (ADR 0019) — jenis tenggat dan siklus ditegakkan basis data, bukan hanya
// validasi aplikasi. Batas 10 = `DeadlineType::MAX_CYCLE`; langkah hanya menyebut submit/output (K5);
// Batas Akhir / Upload Luaran tidak bersiklus. Skema saja: aplikasi belum produksi (pemilik 2026-11-10).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE submission_dates DROP CONSTRAINT submission_dates_cycle_check');
        DB::statement('ALTER TABLE submission_dates ADD CONSTRAINT submission_dates_cycle_check '
            ."CHECK (cycle IS NULL OR (cycle BETWEEN 1 AND 10 AND type <> 'output'))");
        DB::statement('ALTER TABLE submission_dates ADD CONSTRAINT submission_dates_type_check '
            ."CHECK (type IN ('submit', 'evaluation', 'revision', 'output'))");

        DB::statement('ALTER TABLE form_phase_details ADD CONSTRAINT form_phase_details_deadline_type_check '
            ."CHECK (deadline_type IN ('submit', 'output'))");
        DB::statement('ALTER TABLE form_phase_details ADD CONSTRAINT form_phase_details_deadline_cycle_check '
            ."CHECK (deadline_cycle IS NULL OR (deadline_cycle BETWEEN 1 AND 10 AND deadline_type <> 'output'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE form_phase_details DROP CONSTRAINT IF EXISTS form_phase_details_deadline_cycle_check');
        DB::statement('ALTER TABLE form_phase_details DROP CONSTRAINT IF EXISTS form_phase_details_deadline_type_check');
        DB::statement('ALTER TABLE submission_dates DROP CONSTRAINT IF EXISTS submission_dates_type_check');
        DB::statement('ALTER TABLE submission_dates DROP CONSTRAINT submission_dates_cycle_check');
        DB::statement('ALTER TABLE submission_dates ADD CONSTRAINT submission_dates_cycle_check CHECK (cycle IS NULL OR cycle >= 1)');
    }
};
