<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-FE-19, BR-IAM-19 — mencabut dua cascade yang tidak pernah jadi aturan.
 *
 * `form_submissions.form_id` ber-`onDelete('cascade')` sejak 2025. Artinya
 * menghapus satu Form menghapus setiap pengajuannya, lalu delapan tabel ikut
 * cascade dari `form_submission_id`: jawaban, anggota, anggaran, target
 * luaran, reviewer, ringkasan review, luaran, dan override. Kebijakan sebesar
 * itu hanya hidup sebagai satu kata di migrasi, tidak pernah ditulis sebagai
 * business rule, dan tidak pernah disetujui siapa pun.
 *
 * `submitted_by` sama merusaknya dan sempat bisa dipicu pemiliknya sendiri
 * lewat ProfileController::destroy warisan Breeze.
 *
 * Penjagaan di lapis aplikasi (ReferenceGuard, dan BR-IAM-19 di model User)
 * sudah menutup keduanya, tapi selama cascade-nya ada ia bisa dilewati query
 * mana pun yang tidak melewati Eloquent. `restrict` membuat basis data ikut
 * menolak alih-alih ikut menghapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropForeign(['form_id']);
            $table->foreign('form_id')->references('id')->on('forms')
                ->onUpdate('cascade')->onDelete('restrict');

            $table->dropForeign(['submitted_by']);
            $table->foreign('submitted_by')->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropForeign(['form_id']);
            $table->foreign('form_id')->references('id')->on('forms')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->dropForeign(['submitted_by']);
            $table->foreign('submitted_by')->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }
};
