<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pindahkan rule `min_reviewer_count` dari tabel `submission_rules` ke kolom
     * `schemes.rules` (JSONB), lalu hapus tabel `submission_rules` beserta pivot
     * `submission_period_details` yang sudah usang.
     */
    public function up(): void
    {
        // 1. Carry data: tulis nilai global min_reviewer_count ke rules SEMUA scheme.
        //    Bila tabel kosong, biarkan default (Scheme::minReviewerCount() = 2).
        $value = DB::table('submission_rules')
            ->where('label', 'min_reviewer_count')
            ->value('value');

        if ($value !== null) {
            DB::table('schemes')->update([
                'rules' => DB::raw(
                    "COALESCE(rules::jsonb, '{}'::jsonb) || jsonb_build_object('min_reviewer_count', ".(int) $value.')'
                ),
            ]);
        }

        // 2. Drop pivot dulu (punya FK ke submission_rules), baru tabel rules-nya.
        Schema::dropIfExists('submission_period_details');
        Schema::dropIfExists('submission_rules');
    }

    /**
     * Rollback: bangun ulang tabel & kembalikan baris min_reviewer_count dari
     * schemes.rules. Asosiasi period<->rule (isi pivot) tidak bisa direstore.
     */
    public function down(): void
    {
        Schema::create('submission_rules', function (Blueprint $table) {
            $table->id();
            $table->text('label');
            $table->integer('value');
            $table->timestamps();
        });

        Schema::create('submission_period_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_period_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('submission_rule_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });

        $row = DB::table('schemes')
            ->whereRaw("rules ->> 'min_reviewer_count' IS NOT NULL")
            ->selectRaw("(rules ->> 'min_reviewer_count')::int as value")
            ->first();

        if ($row !== null) {
            DB::table('submission_rules')->insert([
                'label' => 'min_reviewer_count',
                'value' => (int) $row->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
