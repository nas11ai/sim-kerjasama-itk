<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-FE-24 — opsi pilihan tidak pernah dihapus permanen.
 *
 * Cermin BR-BUD-05 pada komponen anggaran, dan cermin BR-FE-12 pada field-nya
 * sendiri. `FormFieldResponse.value` menyimpan pilihannya sebagai string tanpa
 * foreign key, jadi opsi yang dihapus keras meninggalkan jawaban yatim: ia
 * menunjuk pilihan yang tidak ada lagi di daftar, tidak bisa dipilih ulang,
 * dan diam-diam hilang dari setiap laporan yang men-join ke opsi berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_field_options', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('form_field_options', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
