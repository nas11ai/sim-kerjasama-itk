<?php

use App\Models\FormType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W91 — sebelum E2 tak satu pun jalur membaca `forms.is_active`, jadi setiap Form berperilaku
 * aktif dan `false` yang tersimpan tak berarti. Disamakan dengan perilaku itu supaya gerbang
 * BR-FE-19 tidak diam-diam menyembunyikan Form saat deploy. Biodata (W61) tetap tidur.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('forms')
            ->where('is_active', false)
            ->where('form_type_id', '!=', FormType::BIODATA_ID)
            ->update(['is_active' => true, 'updated_at' => now()]);
    }

    // Nilai lama tidak bisa dipulihkan dan memang tak pernah bermakna; tidak ada yang dibalik.
    public function down(): void {}
};
