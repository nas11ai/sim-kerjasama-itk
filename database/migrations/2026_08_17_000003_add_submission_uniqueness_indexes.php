<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // BR-MON-03 — satu child submission per phase detail: sebuah
        // parent_submission_id tidak boleh punya lebih dari satu child yang
        // mengarah ke form_phase_detail_id yang sama.
        DB::statement('
            CREATE UNIQUE INDEX one_child_submission_per_phase_detail
            ON form_submissions (parent_submission_id, form_phase_detail_id)
            WHERE parent_submission_id IS NOT NULL
        ');

        // BR-SM-01 — satu submission AKTIF (sudah disubmit, dan belum
        // ditolak/ditarik) per (peneliti, period, scheme).
        //
        // `is_submitted = true` WAJIB ada di predicate. Pada wizard 7 langkah
        // (ADR 0012), scheme_id masih NULL sepanjang langkah 1-2 karena skema
        // baru dipilih peneliti di langkah 3 (ADR 0005). Kalau predicate ini
        // ikut menghitung baris DRAFT, draft kedua peneliti akan ditolak
        // SEBELUM sempat memilih skema — padahal BR-SM-01 eksplisit
        // mengizinkan dua submission ke dua scheme yang berbeda. Dengan
        // membatasi index pada submission yang sudah disubmit, DRAFT bebas
        // dibuat berkali-kali dan baru dibentur pada saat disubmit.
        //
        // `status NOT IN ('rejected', 'withdrawn')` melepaskan submission
        // yang sudah ditolak/ditarik dari index, supaya peneliti boleh
        // resubmit ke period+scheme yang sama.
        //
        // `COALESCE(scheme_id, 0)` sengaja TIDAK dipakai. Index ini per
        // (user, period), bukan per Form: meng-coalesce scheme_id akan salah
        // memblokir submission scheme-less KEDUA ke Form lain pada period
        // yang sama. Perilaku bawaan unique index PostgreSQL sudah pas untuk
        // ini — setiap NULL dianggap berbeda dari NULL lainnya, jadi dua
        // baris scheme_id NULL tidak pernah dianggap duplikat.
        DB::statement("
            CREATE UNIQUE INDEX one_active_submission_per_period_scheme
            ON form_submissions (submitted_by, submission_period_id, scheme_id)
            WHERE parent_submission_id IS NULL
              AND is_submitted = true
              AND status NOT IN ('rejected', 'withdrawn')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS one_child_submission_per_phase_detail');
        DB::statement('DROP INDEX IF EXISTS one_active_submission_per_period_scheme');
    }
};
