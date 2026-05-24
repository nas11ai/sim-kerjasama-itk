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

            // 1. DROP FOREIGN KEY DULU (INI YANG KAMU LUPA URUTANNYA)
            $table->dropForeign(['form_phase_id']);

            // 2. BARU DROP INDEX
            $table->dropIndex('review_evaluation_forms_form_phase_id_is_active_index');
            $table->dropIndex('review_evaluation_forms_form_phase_id_order_index');

            // 3. DROP COLUMN
            $table->dropColumn('form_phase_id');

            // 4. ADD NEW COLUMN
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

            // drop new FK
            $table->dropForeign(['form_phase_detail_id']);
            $table->dropColumn('form_phase_detail_id');

            // restore old column
            $table->foreignId('form_phase_id')
                ->constrained()
                ->onDelete('cascade');

            $table->index(['form_phase_id', 'is_active']);
            $table->index(['form_phase_id', 'order']);
        });
    }
};
