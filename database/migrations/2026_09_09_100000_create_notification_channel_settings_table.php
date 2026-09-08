<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-NOT-06 — channel per tipe event adalah konfigurasi, bukan konstanta kode.
 *
 * Mengikuti preseden `scheme.rules` (BR-SCH-06): di-seed dengan default yang
 * masuk akal, lalu disunting Admin tanpa deploy. Yang menjadi aturan hanya
 * satu — setiap tipe event wajib punya deklarasi; isinya bukan aturan, jadi
 * daftarnya tidak dibekukan di DDD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channel_settings', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 191)->unique();
            $table->jsonb('channels');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channel_settings');
    }
};
