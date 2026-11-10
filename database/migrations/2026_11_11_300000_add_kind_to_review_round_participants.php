<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BR-REV-16 — jenis kursi per peserta: pemilik catatan yang bergabung ke putaran penilaian saat
// kirim ulang tetap memeriksa, bukan menilai. Baris lama mengikuti jenis putarannya; null dibaca
// sama dengan jenis putaran (ReviewRoundParticipant::effectiveKind).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_round_participants', function (Blueprint $table) {
            $table->string('kind')->nullable()->after('submission_reviewer_id');
        });

        DB::table('review_round_participants')->whereNull('kind')->update([
            'kind' => DB::raw('(select r.kind from review_rounds r where r.id = review_round_participants.review_round_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('review_round_participants', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
