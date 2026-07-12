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
    }

    public function down(): void
    {
        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->dropForeign(['submission_date_id']);
            $table->dropColumn('submission_date_id');
        });
    }
};
