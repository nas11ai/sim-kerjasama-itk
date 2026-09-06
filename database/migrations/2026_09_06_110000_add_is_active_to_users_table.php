<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-IAM-05 — deaktivasi menyetel `is_active = false` dan tidak menghapus
 * apa pun; data user tetap utuh supaya jejak pengajuan lama tetap terbaca.
 *
 * Dipakai juga BR-SM-09/BR-IAM-10: lead researcher tidak boleh dinonaktifkan
 * selama masih memegang pengajuan aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_reviewer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
