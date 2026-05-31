<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_field_responses', function (Blueprint $table) {
            $table->dropForeign(['form_field_id']);

            $table->foreign('form_field_id')
                ->references('id')
                ->on('form_fields')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_field_responses', function (Blueprint $table) {
            $table->dropForeign(['form_field_id']);

            $table->foreign('form_field_id')
                ->references('id')
                ->on('form_fields')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }
};
