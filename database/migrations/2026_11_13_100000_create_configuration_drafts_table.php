<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-FE-31 (K2) — draf konfigurasi sebagai satu baris JSON per objek. `draftable_type` konstanta
 * string (G2-W7, tanpa morph map); R6 menambah jenis lain tanpa tabel baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('draftable_type', 64);
            $table->unsignedBigInteger('draftable_id');
            $table->jsonb('payload');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['draftable_type', 'draftable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_drafts');
    }
};
