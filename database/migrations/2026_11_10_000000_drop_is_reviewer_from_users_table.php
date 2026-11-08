<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// fe-v2-design W162 — kolom ini tak pernah ditulis aplikasi; accessor `is_reviewer` yang dulu
// menutupinya membaca masa tugas (BR-REV-09, ReviewersWithinTenure), bukan kolom ini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_reviewer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_reviewer')->default(false)->after('password');
        });
    }
};
