<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BR-FE-25/26 (K8a, K8b, K8f) — label teks bebas diganti jenis tenggat tetap × siklus, dan
 * detail tahap memegang tenggat per aktor. Aplikasi belum live: tanpa migrasi data, DB dev
 * di-`migrate:fresh`. `down()` memulihkan struktur, bukan isinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_dates', function (Blueprint $table) {
            $table->dropForeign(['submission_date_label_id']);
            $table->dropColumn('submission_date_label_id');
            $table->string('type');
            $table->smallInteger('cycle')->nullable();
        });

        DB::statement('ALTER TABLE submission_dates ADD CONSTRAINT submission_dates_cycle_check CHECK (cycle IS NULL OR cycle >= 1)');
        // Blueprint belum mendukung NULLS NOT DISTINCT (PostgreSQL ≥ 15): tanpa itu dua
        // Batas Submit pengajuan utama (siklus null) lolos dari unik.
        DB::statement('CREATE UNIQUE INDEX submission_dates_period_type_cycle_unique ON submission_dates (submission_period_id, type, cycle) NULLS NOT DISTINCT');

        Schema::dropIfExists('submission_date_labels');

        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->foreignId('evaluation_date_id')->nullable()->constrained('submission_dates')->restrictOnDelete();
            $table->foreignId('revision_date_id')->nullable()->constrained('submission_dates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_date_id');
            $table->dropConstrainedForeignId('revision_date_id');
        });

        Schema::create('submission_date_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::statement('DROP INDEX submission_dates_period_type_cycle_unique');

        Schema::table('submission_dates', function (Blueprint $table) {
            $table->dropColumn(['type', 'cycle']);
        });

        Schema::table('submission_dates', function (Blueprint $table) {
            $table->foreignId('submission_date_label_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
        });
    }
};
