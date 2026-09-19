<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-SM-21 — mitra eksternal sebuah pengajuan.
 *
 * `output_type = 'pks'` sudah menyimpan `involved_parties` sebagai teks bebas
 * di metadata JSON-nya, tapi itu kemitraan yang **sudah terwujud** dan dalam
 * bentuk yang tidak bisa ditanyai. Tabel ini mencatat mitra **pada saat
 * diajukan**, sehingga "berapa pengajuan yang melibatkan institusi ini" jadi
 * pertanyaan yang punya jawaban.
 *
 * Nama institusi tidak dinormalisasi jadi tabel acuan sendiri: daftar mitra
 * LPPM tidak pernah tertutup, dan memaksanya memilih dari daftar berarti
 * menolak mitra yang baru pertama kali datang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->string('institution_name');
            $table->string('contact_person')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            // Dicari per institusi saat melaporkan cakupan kemitraan.
            $table->index('institution_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_partners');
    }
};
