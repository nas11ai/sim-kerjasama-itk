<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-SM-20 — anggota mahasiswa dicatat sebagai data, bukan akun.
 *
 * Mahasiswa tidak mendaftar ke SIMPAS, jadi barisnya tidak menunjuk
 * `user_profiles` seperti `research_members`. Memaksanya punya akun berarti
 * menuntut ratusan pendaftaran yang tidak pernah dipakai untuk apa pun.
 *
 * NIM unik per pengajuan, bukan global: satu mahasiswa memang boleh terlibat
 * di beberapa penelitian, dan keunikan global akan menolak yang kedua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('student_id');
            $table->string('study_program')->nullable();
            $table->timestamps();

            $table->unique(['form_submission_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_members');
    }
};
