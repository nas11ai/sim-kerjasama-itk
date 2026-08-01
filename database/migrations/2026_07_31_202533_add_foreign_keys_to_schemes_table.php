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
        Schema::table('schemes', function (Blueprint $table) {
            $table->foreign('scheme_type_id')
                ->references('id')
                ->on('scheme_types')
                ->nullOnDelete();

            $table->foreign('submission_type_id')
                ->references('id')
                ->on('submission_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schemes', function (Blueprint $table) {
            $table->dropForeign(['scheme_type_id']);
            $table->dropForeign(['submission_type_id']);
        });
    }
};
