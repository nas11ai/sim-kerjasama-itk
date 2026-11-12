<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-FE-34 — kapan field Form pertama kali diterbitkan. Null = belum pernah terbit, jadi Form
 * belum bisa dipakai langkah tahap (G3-W6). Aplikasi belum produksi: tanpa migrasi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
