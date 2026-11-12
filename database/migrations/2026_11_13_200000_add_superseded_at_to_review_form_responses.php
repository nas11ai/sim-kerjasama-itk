<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-REV-19 — jawaban yang digantikan versi baru menjadi riwayat: barisnya tetap, hanya ditandai.
 * Penugasan memakai jawaban yang `superseded_at`-nya kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_form_responses', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->after('locked_at');
            $table->index(['reviewer_form_assignment_id', 'superseded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('review_form_responses', function (Blueprint $table) {
            $table->dropIndex(['reviewer_form_assignment_id', 'superseded_at']);
            $table->dropColumn('superseded_at');
        });
    }
};
