<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cermin BR-FE-24 pada opsi field evaluasi reviewer. `ReviewFormFieldResponse.value` menyimpan
 * pilihannya sebagai string tanpa foreign key, jadi opsi yang dihapus keras membuat jawaban lama
 * kehilangan labelnya: formatter hanya bisa menampilkan nilai mentahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_form_field_options', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('review_form_field_options', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
