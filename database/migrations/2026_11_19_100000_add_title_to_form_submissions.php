<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-SM-22 — judul pengajuan, salinan jawaban field `submission_title`. Null = Form tanpa field
 * itu atau jawabannya kosong; keduanya tampil dengan judul Form. `text`, karena jawaban teks
 * wizard tidak dibatasi panjangnya. Tanpa migrasi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->text('title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
