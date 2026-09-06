<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-REV-07 — penolakan wajib beralasan.
 *
 * Penolakan menutup pengajuan selamanya, dan peneliti berhak tahu kenapa.
 * Tanpa kolom ini alasannya hanya hidup di notifikasi yang bisa terhapus,
 * dan mekanisme banding jadi mustahil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
