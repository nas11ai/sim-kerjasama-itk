<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->foreignId('form_phase_detail_id')->nullable()
                ->constrained('form_phase_details')->nullOnDelete();
            $table->foreignId('submission_period_id')->nullable()
                ->constrained('submission_periods')->nullOnDelete();
            $table->foreignId('scheme_id')->nullable()
                ->constrained('schemes')->nullOnDelete();
            $table->foreignId('trl_id')->nullable()
                ->constrained('technology_readiness_levels')->nullOnDelete();
            $table->smallInteger('last_completed_step')->default(0);
        });

        $this->guardAgainstAmbiguousPhaseDetails();
        $this->backfillPhaseDetail();
        $this->backfillPeriod();
    }

    /**
     * Inferensi lama hanya sah kalau satu Form dipakai tepat satu phase detail.
     * Kalau ada yang ambigu, migration berhenti — tidak menebak.
     */
    private function guardAgainstAmbiguousPhaseDetails(): void
    {
        $ambiguous = DB::select('
            SELECT fs.id, COUNT(DISTINCT fpd.id) AS candidates
            FROM form_submissions fs
            JOIN form_access_controls fac ON fac.form_id = fs.form_id
            JOIN form_phase_details fpd  ON fpd.form_access_control_id = fac.id
            GROUP BY fs.id
            HAVING COUNT(DISTINCT fpd.id) > 1
        ');

        if ($ambiguous !== []) {
            $ids = implode(', ', array_column($ambiguous, 'id'));

            throw new RuntimeException(
                'Backfill dibatalkan: form_submissions berikut punya lebih dari satu '
                ."kandidat form_phase_detail dan harus diselesaikan manual — id: {$ids}"
            );
        }
    }

    private function backfillPhaseDetail(): void
    {
        DB::statement('
            UPDATE form_submissions fs
            SET form_phase_detail_id = sub.detail_id
            FROM (
                SELECT fs2.id AS submission_id, MIN(fpd.id) AS detail_id
                FROM form_submissions fs2
                JOIN form_access_controls fac ON fac.form_id = fs2.form_id
                JOIN form_phase_details fpd  ON fpd.form_access_control_id = fac.id
                GROUP BY fs2.id
            ) AS sub
            WHERE fs.id = sub.submission_id
        ');
    }

    /**
     * Period diturunkan lewat form_phase_detail → submission_date → submission_period.
     * Jalur submission_period_phases TIDAK dipakai: pivot itu tanpa unique constraint,
     * satu FormPhase boleh dimiliki banyak period.
     */
    private function backfillPeriod(): void
    {
        DB::statement('
            UPDATE form_submissions fs
            SET submission_period_id = sd.submission_period_id
            FROM form_phase_details fpd
            JOIN submission_dates sd ON sd.id = fpd.submission_date_id
            WHERE fpd.id = fs.form_phase_detail_id
        ');
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('form_phase_detail_id');
            $table->dropConstrainedForeignId('submission_period_id');
            $table->dropConstrainedForeignId('scheme_id');
            $table->dropConstrainedForeignId('trl_id');
            $table->dropColumn('last_completed_step');
        });
    }
};
