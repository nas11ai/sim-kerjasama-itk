<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('form_submissions')
            ->where('status', 'pending')
            ->update(['status' => 'submitted']);

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        DB::table('form_submissions')
            ->where('status', 'submitted')
            ->update(['status' => 'pending']);
    }
};
