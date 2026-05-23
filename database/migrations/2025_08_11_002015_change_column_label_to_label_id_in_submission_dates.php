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
        Schema::table('submission_dates', function (Blueprint $table) {
            $table->dropColumn('label');
            $table->dropColumn('date');
        });

        Schema::table('submission_dates', function (Blueprint $table) {
            $table->foreignId('submission_date_label_id')
                ->after('id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->dateTime('datetime')
                ->after('submission_date_label_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submission_dates', function (Blueprint $table) {
            $table->dropForeign(['submission_date_label_id']);
            $table->dropColumn('submission_date_label_id');
            $table->dropColumn('datetime');
            $table->string('label')->after('id');
            $table->date('date')->after('label');
        });
    }
};
