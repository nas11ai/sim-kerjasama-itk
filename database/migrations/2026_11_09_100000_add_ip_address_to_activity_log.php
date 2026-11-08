<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// W155 — DDD Reporting: Audit Log "dari IP mana". Entri lama tetap null: append-only
// (BR-RPT-04) melarang mengisi ulang sejarah.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('causer_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
