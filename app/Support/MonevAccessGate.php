<?php

namespace App\Support;

use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use Illuminate\Validation\ValidationException;

/**
 * BR-MON-01, BR-MON-02, BR-MON-08 — kapan sebuah siklus monev boleh dikerjakan.
 *
 * Tiga syarat yang harus lolos bersamaan, dan ketiganya menjawab pertanyaan
 * berbeda:
 *
 *   1. **Keadaan induk** (BR-MON-01) — monev memantau kegiatan yang sudah
 *      berjalan; selama pengajuannya belum disetujui, tidak ada yang dipantau.
 *   2. **Urutan siklus** (BR-MON-02) — detail sebelumnya harus `canProceed()`.
 *      Turunan keadaan, bukan kolom; tidak ada tombol "buka paksa".
 *   3. **Pembekuan** (BR-MON-08) — penarikan menghentikan laporan baru.
 *
 * Batas waktu per detail (BR-MON-05) tidak diulang di sini: ia penerapan
 * BR-FE-08 yang sudah dijaga `PhaseAccessGate`, dan menuliskannya dua kali
 * berarti dua tempat yang bisa menyimpang.
 */
final class MonevAccessGate
{
    /**
     * @throws ValidationException
     */
    public static function assertCycleOpen(FormSubmission $parent, FormPhaseDetail $detail): void
    {
        // BR-MON-08 — diperiksa lebih dulu: pembekuan mengalahkan segalanya,
        // dan pesannya paling menjelaskan.
        if ($parent->status instanceof Withdrawn) {
            throw ValidationException::withMessages([
                'parent_submission_id' => 'Pengajuan ini sudah ditarik. Siklus monevnya dibekukan dan '
                    .'tidak menerima laporan baru.',
            ]);
        }

        if (!$parent->status instanceof Approved) {
            throw ValidationException::withMessages([
                'parent_submission_id' => 'Siklus monev terbuka setelah pengajuan disetujui.',
            ]);
        }

        $blocking = self::unfinishedPredecessor($parent, $detail);

        if ($blocking !== null) {
            throw ValidationException::withMessages([
                'form_phase_detail_id' => 'Selesaikan "'.self::labelFor($blocking).'" lebih dulu — '
                    .'tahapannya berurutan.',
            ]);
        }
    }

    public static function isCycleOpen(FormSubmission $parent, FormPhaseDetail $detail): bool
    {
        if ($parent->status instanceof Withdrawn || !$parent->status instanceof Approved) {
            return false;
        }

        return self::unfinishedPredecessor($parent, $detail) === null;
    }

    /**
     * BR-MON-02 — detail sebelumnya yang belum tuntas, kalau ada.
     *
     * Yang diperiksa adalah laporan **milik induk ini** pada detail-detail
     * sebelumnya. Detail yang belum pernah punya laporan sama-sama menghalangi
     * dengan yang laporannya belum disubmit — keduanya berarti tahap itu belum
     * selesai.
     */
    private static function unfinishedPredecessor(
        FormSubmission $parent,
        FormPhaseDetail $detail
    ): ?FormPhaseDetail {
        $predecessors = FormPhaseDetail::query()
            ->where('form_phase_id', $detail->form_phase_id)
            ->where('order', '<', $detail->order)
            // Detail pengajuan awal bukan siklus monev: ketuntasannya sudah
            // dinyatakan oleh status APPROVED induknya.
            ->where('id', '!=', $parent->form_phase_detail_id)
            ->orderBy('order')
            ->get();

        foreach ($predecessors as $predecessor) {
            $report = FormSubmission::query()
                ->where('parent_submission_id', $parent->id)
                ->where('form_phase_detail_id', $predecessor->id)
                ->first();

            if ($report === null || !$report->canProceed()) {
                return $predecessor;
            }
        }

        return null;
    }

    private static function labelFor(FormPhaseDetail $detail): string
    {
        $phase = $detail->formPhase;

        return $phase instanceof FormPhase ? $phase->title : 'tahap sebelumnya';
    }
}
