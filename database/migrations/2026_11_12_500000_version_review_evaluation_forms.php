<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-REV-18 (K1, K3) — Form Evaluasi jadi pustaka berversi: field milik versi (salin baris), pemasangan
 * ke langkah lewat `step_evaluation_forms`, versi dipaku per angkatan × langkah × Form Evaluasi.
 * Skema saja: belum produksi, DB dev di-`migrate:fresh --seed` (roadmap fase reuse).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_evaluation_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_evaluation_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['review_evaluation_form_id', 'number']);
        });

        Schema::table('review_form_fields', function (Blueprint $table) {
            $table->dropIndex(['review_evaluation_form_id', 'order']);
            $table->dropConstrainedForeignId('review_evaluation_form_id');
            $table->foreignId('review_evaluation_form_version_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['review_evaluation_form_version_id', 'order']);
        });

        Schema::table('review_evaluation_forms', function (Blueprint $table) {
            $table->dropIndex(['form_phase_detail_id', 'is_active']);
            $table->dropIndex(['form_phase_detail_id', 'order']);
            $table->dropConstrainedForeignId('form_phase_detail_id');
            $table->dropColumn(['is_required', 'order', 'is_active']);
        });

        Schema::create('step_evaluation_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_phase_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_evaluation_form_id')->constrained()->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('order');
            $table->timestamps();

            $table->unique(['form_phase_detail_id', 'review_evaluation_form_id']);
        });

        Schema::create('evaluation_form_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_phase_detail_id')->constrained()->restrictOnDelete();
            $table->foreignId('review_evaluation_form_id')->constrained()->restrictOnDelete();
            $table->foreignId('review_evaluation_form_version_id')->constrained()->restrictOnDelete();
            $table->timestamp('pinned_at');
            $table->timestamps();

            $table->unique(['submission_period_id', 'form_phase_detail_id', 'review_evaluation_form_id'], 'evaluation_form_pins_cohort_unique');
        });

        Schema::table('review_form_responses', function (Blueprint $table) {
            $table->foreignId('review_evaluation_form_version_id')->after('reviewer_form_assignment_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('review_form_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_evaluation_form_version_id');
        });

        Schema::dropIfExists('evaluation_form_pins');
        Schema::dropIfExists('step_evaluation_forms');

        Schema::table('review_evaluation_forms', function (Blueprint $table) {
            $table->foreignId('form_phase_detail_id')->after('description')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->index(['form_phase_detail_id', 'is_active']);
            $table->index(['form_phase_detail_id', 'order']);
        });

        Schema::table('review_form_fields', function (Blueprint $table) {
            $table->dropIndex(['review_evaluation_form_version_id', 'order']);
            $table->dropConstrainedForeignId('review_evaluation_form_version_id');
            $table->foreignId('review_evaluation_form_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['review_evaluation_form_id', 'order']);
        });

        Schema::dropIfExists('review_evaluation_form_versions');
    }
};
