<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BR-REV-16, spec §4.10 — pengajuan utama yang sedang menunggu saat rilis mendapat satu putaran.
 * Query builder, bukan Model/Action, supaya migration tak ikut berubah bersama kode; tanpa
 * notifikasi dan tanpa transisi status (keputusan rencana P1-W4).
 */
return new class extends Migration
{
    private const AWAITING_DECISION = ['submitted', 'under_review', 'resubmitted'];

    /** `ResubmitFormSubmission` mencatat deskripsi ini; jejaknya penanda putaran pemeriksaan. */
    private const RESUBMIT_AUDIT = 'Pengajuan dikirim ulang setelah revisi';

    private const SUBMISSION_MORPH = 'App\\Models\\FormSubmission';

    public function up(): void
    {
        // Hanya yang belum punya putaran: aman dijalankan ulang.
        $pending = DB::table('form_submissions as s')
            ->whereNull('s.parent_submission_id')
            ->whereIn('s.status', self::AWAITING_DECISION)
            ->whereExists(fn ($seat) => $seat->from('submission_reviewers')
                ->whereColumn('form_submission_id', 's.id')->where('status', 'active'))
            ->whereNotExists(fn ($round) => $round->from('review_rounds')->whereColumn('form_submission_id', 's.id'))
            ->pluck('s.id');

        foreach ($pending as $submissionId) {
            DB::transaction(fn () => $this->backfill((int) $submissionId));
        }
    }

    private function backfill(int $submissionId): void
    {
        $recheck = DB::table('activity_log')
            ->where('subject_type', self::SUBMISSION_MORPH)
            ->where('subject_id', $submissionId)
            ->where('description', self::RESUBMIT_AUDIT)
            ->exists();

        $seats = $recheck ? $this->seatsWithOpenNotes($submissionId) : $this->activeSeats($submissionId);
        // Putaran pemeriksaan tanpa peserta: semua catatan sudah ditutup, jadi langsung siap.
        $concluded = $recheck && $seats->isEmpty();
        $now = now();

        $roundId = DB::table('review_rounds')->insertGetId([
            'form_submission_id' => $submissionId,
            'number' => 1,
            'kind' => $recheck ? 'recheck' : 'assessment',
            'completed_at' => $concluded ? $now : null,
            'outcome' => $concluded ? 'ready' : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Spec §4.10 — di aturan lama reviewer `completed` sudah boleh membuka catatan, jadi di
        // putaran penilaian ia dihitung sudah Selesai.
        DB::table('review_round_participants')->insert($seats->map(fn (object $seat): array => [
            'review_round_id' => $roundId,
            'submission_reviewer_id' => $seat->id,
            'finished_at' => !$recheck && $seat->evaluation_status === 'completed' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }

    /** @return Collection<int, object> */
    private function activeSeats(int $submissionId): Collection
    {
        return DB::table('submission_reviewers')
            ->where('form_submission_id', $submissionId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'reviewer_id', 'evaluation_status']);
    }

    /** @return Collection<int, object> */
    private function seatsWithOpenNotes(int $submissionId): Collection
    {
        $holders = DB::table('review_summaries')
            ->where('form_submission_id', $submissionId)
            ->where('status', 'open')
            ->select('reviewer_id');

        return $this->activeSeats($submissionId)->whereIn('reviewer_id', $holders->pluck('reviewer_id')->all())->values();
    }

    /**
     * Sengaja kosong: tabelnya dicabut migration 2026_11_11_100000, dan putaran yang sudah
     * berjalan sejak backfill tidak bisa dibedakan dari putaran hasil backfill.
     */
    public function down(): void {}
};
