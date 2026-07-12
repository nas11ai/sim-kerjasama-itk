<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->foreignId('submission_date_id')
                ->nullable()
                ->constrained('submission_dates')
                ->after('phase_type_id');
        });

        // Backfill: assign first submission_date dari period yang sama
        DB::statement("
            UPDATE form_phase_details fpd
            SET submission_date_id = (
                SELECT sd.id
                FROM submission_dates sd
                JOIN submission_period_phases spp ON spp.submission_period_id = sd.submission_period_id
                WHERE spp.form_phase_id = fpd.form_phase_id
                ORDER BY sd.date ASC
                LIMIT 1
            )
            WHERE fpd.submission_date_id IS NULL
        ");

        DB::statement('ALTER TABLE form_phase_details ALTER COLUMN submission_date_id SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->dropForeign(['submission_date_id']);
            $table->dropColumn('submission_date_id');
        });
    }
};
