<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Form baru aktif secara bawaan (selaras FormFactory/CreateForm). Hanya bawaan; baris lama tidak diubah.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->change();
        });
    }
};
