<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('review_evaluation_forms', function (Blueprint $table) {
            // Drop existing foreign key and column
            $table->dropForeign(['form_phase_id']);
            $table->dropColumn('form_phase_id');

            // Add new column and foreign key
            $table->foreignId('form_phase_detail_id')
                ->after('description')
                ->constrained('form_phase_details')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_evaluation_forms', function (Blueprint $table) {
            // Drop existing foreign key and column
            $table->dropForeign(['form_phase_id']);
            $table->dropColumn('form_phase_id');

            // Add new column and foreign key
            $table->foreignId('form_phase_detail_id')
                ->after('description')
                ->constrained('form_phase_details')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }
};
