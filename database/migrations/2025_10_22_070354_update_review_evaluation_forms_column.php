<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_evaluation_forms', function (Blueprint $table) {
            // Drop foreign key lama terlebih dahulu
            $table->dropForeign(['form_phase_id']);

            // Drop index lama yang memang ada
            $table->dropIndex(['form_phase_id', 'is_active']);
            $table->dropIndex(['form_phase_id', 'order']);

            // Drop kolom lama
            $table->dropColumn('form_phase_id');

            // Tambah kolom baru
            $table->foreignId('form_phase_detail_id')
                ->after('description')
                ->constrained('form_phase_details')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Tambah index baru
            $table->index(['form_phase_detail_id', 'is_active']);
            $table->index(['form_phase_detail_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::table('review_evaluation_forms', function (Blueprint $table) {
            // Drop foreign key baru
            $table->dropForeign(['form_phase_detail_id']);

            // Drop index baru
            $table->dropIndex(['form_phase_detail_id', 'is_active']);
            $table->dropIndex(['form_phase_detail_id', 'order']);


            // Drop kolom baru
            $table->dropColumn('form_phase_detail_id');

            // Restore kolom lama
            $table->foreignId('form_phase_id')
                ->after('description')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Restore index lama
            $table->index(['form_phase_id', 'is_active']);
            $table->index(['form_phase_id', 'order']);
        });
    }
};
