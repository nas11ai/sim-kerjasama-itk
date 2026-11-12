<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BR-REV-19 — satu jawaban berlaku per penugasan; riwayat (`superseded_at` terisi) boleh banyak.
 * Dua "mulai mengisi" bersamaan bertemu di sini, dan OpenEvaluationResponse membaca pemenangnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX review_form_responses_current_unique ON review_form_responses (reviewer_form_assignment_id) WHERE superseded_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX review_form_responses_current_unique');
    }
};
