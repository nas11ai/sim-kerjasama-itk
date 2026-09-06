<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taksonomi tipe luaran — dipakai bersama oleh target yang dijanjikan
 * (BR-SM-14) dan luaran yang terealisasi (BR-RO-05).
 *
 * Sengaja tabel, bukan enum di kode: BR-RO-05 menuntut tipe baru yang
 * terdaftar langsung bisa dipakai tanpa migration. Menambah tipe adalah
 * perubahan data, bukan perubahan skema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('output_type_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('output_type_definitions');
    }
};
