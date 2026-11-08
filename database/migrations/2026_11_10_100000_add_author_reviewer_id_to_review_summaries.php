<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BR-REV-15, W164 — penulis ulir terpisah dari pemegangnya (`reviewer_id`, berpindah saat
// penggantian). Restrict, bukan cascade: riwayat penulis tidak ikut terhapus (BR-REV-10).
// Baris lama diisi pemegangnya; aplikasi belum live, jadi tidak ditelusuri dari audit.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('author_reviewer_id')->nullable()->after('reviewer_id');
        });

        DB::table('review_summaries')->update(['author_reviewer_id' => DB::raw('reviewer_id')]);

        Schema::table('review_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('author_reviewer_id')->nullable(false)->change();
            $table->foreign('author_reviewer_id')->references('id')->on('reviewers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('review_summaries', function (Blueprint $table) {
            $table->dropForeign(['author_reviewer_id']);
            $table->dropColumn('author_reviewer_id');
        });
    }
};
