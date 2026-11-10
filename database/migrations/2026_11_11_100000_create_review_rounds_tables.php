<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BR-REV-16, ADR 0018 — putaran review dan tanda Selesai per reviewer per putaran. Riwayat
// putaran lama tetap tersimpan: satu baris per putaran, bernomor urut per pengajuan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('kind');
            $table->timestamp('completed_at')->nullable();
            // `needs_revision` | `ready`; null selama putaran berjalan.
            $table->string('outcome')->nullable();
            $table->timestamps();

            $table->unique(['form_submission_id', 'number']);
        });

        Schema::create('review_round_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_round_id')->constrained('review_rounds')->cascadeOnDelete();
            $table->foreignId('submission_reviewer_id')->constrained('submission_reviewers');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['review_round_id', 'submission_reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_round_participants');
        Schema::dropIfExists('review_rounds');
    }
};
