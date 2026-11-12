<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-FE-32 (K1, K3) — struktur tahap berversi: langkah milik versi (salin baris), versi dipaku per
 * angkatan (Periode × Tahap). Skema saja: belum produksi, DB dev di-`migrate:fresh --seed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_phase_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_phase_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['form_phase_id', 'number']);
            // Sasaran FK komposit: langkah dan pin tidak bisa menunjuk versi milik tahap lain.
            $table->unique(['id', 'form_phase_id']);
        });

        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->unsignedBigInteger('form_phase_version_id')->after('form_phase_id');
            $table->foreign(['form_phase_version_id', 'form_phase_id'])
                ->references(['id', 'form_phase_id'])->on('form_phase_versions')->cascadeOnDelete();
            $table->index(['form_phase_version_id', 'order']);
        });

        Schema::create('phase_version_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_phase_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('form_phase_version_id');
            $table->timestamp('pinned_at');
            $table->timestamps();

            $table->foreign(['form_phase_version_id', 'form_phase_id'])
                ->references(['id', 'form_phase_id'])->on('form_phase_versions');
            $table->unique(['submission_period_id', 'form_phase_id'], 'phase_version_pins_cohort_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_version_pins');

        Schema::table('form_phase_details', function (Blueprint $table) {
            $table->dropForeign(['form_phase_version_id', 'form_phase_id']);
            $table->dropIndex(['form_phase_version_id', 'order']);
            $table->dropColumn('form_phase_version_id');
        });

        Schema::dropIfExists('form_phase_versions');
    }
};
