<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->foreignId('submission_date_id')
                ->nullable()
                ->constrained('submission_dates')
                ->after('phase_type_id');
        });

        $defaultLabelId = DB::table('submission_date_labels')->min('id');

        $periodsWithoutDates = DB::table('form_phase_details AS fpd')
            ->join('form_phases AS fp', 'fp.id', '=', 'fpd.form_phase_id')
            ->join('submission_period_phases AS spp', 'spp.form_phase_id', '=', 'fp.id')
            ->leftJoin('submission_dates AS sd', 'sd.submission_period_id', '=', 'spp.submission_period_id')
            ->whereNull('sd.id')
            ->whereNull('fpd.submission_date_id')
            ->select('spp.submission_period_id')
            ->distinct()
            ->pluck('submission_period_id');

        foreach ($periodsWithoutDates as $periodId) {
            DB::table('submission_dates')->insert([
                'submission_date_label_id' => $defaultLabelId,
                'datetime' => now(),
                'submission_period_id' => $periodId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::statement('
            UPDATE form_phase_details fpd
            SET fpd.submission_date_id = (
                SELECT sd.id
                FROM form_phases fp
                JOIN submission_period_phases spp ON spp.form_phase_id = fp.id
                JOIN submission_dates sd ON sd.submission_period_id = spp.submission_period_id
                WHERE fp.id = fpd.form_phase_id
                ORDER BY sd.datetime ASC
                LIMIT 1
            )
            WHERE fpd.submission_date_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->dropForeign(['submission_date_id']);
            $table->dropColumn('submission_date_id');
        });
    }
};
