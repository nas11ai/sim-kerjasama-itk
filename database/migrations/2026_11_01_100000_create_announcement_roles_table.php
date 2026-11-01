<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * fe-v2-design §4.7 — target peran pengumuman privat. Tanpa baris = semua pengguna login;
 * dengan baris = pengguna yang memegang minimal satu peran itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_roles', function (Blueprint $table): void {
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->unique(['announcement_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_roles');
    }
};
