<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-FE-35 — teks pertanyaan dan label opsi saat submit. Null = belum pernah disubmit, atau
 * disubmit sebelum aturan ini ada; keduanya dibaca dengan teks terkini. Tanpa migrasi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->jsonb('submitted_field_labels')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('submitted_field_labels');
        });
    }
};
