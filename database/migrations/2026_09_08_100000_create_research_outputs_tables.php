<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BR-RO-01 s/d BR-RO-09 — luaran penelitian yang benar-benar terwujud.
 *
 * Satu tabel generik dengan metadata JSONB: menambah tipe luaran baru tidak
 * butuh migration (BR-RO-05). Research Output **bukan** child FormSubmission —
 * lifecycle-nya berbeda, jadi ia punya tabelnya sendiri.
 *
 * Definisi tipenya ikut dilengkapi di sini. `fields` menyimpan spesifikasi
 * metadata per tipe (BR-RO-09) dan `requires_files` menandai tipe yang wajib
 * berlampiran (BR-RO-04). Keduanya masuk **tabel**, bukan config kode: kalau
 * menambah tipe masih menuntut menyunting kode untuk mendefinisikan field-nya,
 * janji BR-RO-05 baru terpenuhi separuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('output_type_definitions', function (Blueprint $table) {
            $table->jsonb('fields')->nullable()->after('name');
            $table->boolean('requires_files')->default(false)->after('fields');
            // BR-RO-02 — tipe yang hanya sah untuk jenis pengajuan tertentu.
            // NULL berarti berlaku untuk semua jenis.
            $table->string('submission_type')->nullable()->after('requires_files');
        });

        Schema::create('research_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->string('output_type');
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('output_type');
        });

        // GIN index untuk pencarian di dalam metadata — pelaporan LPPM
        // menanyakan isi JSONB, bukan hanya tipenya.
        DB::statement('CREATE INDEX idx_research_outputs_metadata ON research_outputs USING GIN (metadata)');

        Schema::create('research_output_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_output_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_output_files');
        Schema::dropIfExists('research_outputs');

        Schema::table('output_type_definitions', function (Blueprint $table) {
            $table->dropColumn(['fields', 'requires_files', 'submission_type']);
        });
    }
};
