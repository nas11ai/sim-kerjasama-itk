<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// W126, BR-MON-10 — kapan Operator mengaktifkan siklus sebuah laporan monev. Hanya laporan anak
// yang mengisinya; tanpa backfill (siklus lama dianggap belum diaktifkan).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->timestampTz('cycle_opened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('cycle_opened_at');
        });
    }
};
