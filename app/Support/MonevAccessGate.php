<?php

namespace App\Support;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Queries\Monev\MonevCycles;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use Illuminate\Validation\ValidationException;

/**
 * BR-MON-01, BR-MON-02, BR-MON-08 — kapan siklus monev boleh dikerjakan: tiga
 * syarat yang harus lolos bersamaan. BR-MON-05 tidak diulang di sini — ia
 * penerapan BR-FE-08 milik `PhaseAccessGate`, dan dua tempat bisa menyimpang.
 */
final class MonevAccessGate
{
    /**
     * @throws ValidationException
     */
    public static function assertCycleOpen(FormSubmission $parent, FormPhaseDetail $detail): void
    {
        $reason = self::refusal($parent, $detail);

        if ($reason === null) {
            return;
        }

        throw ValidationException::withMessages([$reason[0] => $reason[1]]);
    }

    public static function isCycleOpen(FormSubmission $parent, FormPhaseDetail $detail): bool
    {
        return self::refusal($parent, $detail) === null;
    }

    /**
     * Alasan penolakan pertama sebagai [kunci error, pesan], atau null bila terbuka.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function refusal(FormSubmission $parent, FormPhaseDetail $detail): ?array
    {
        // BR-MON-08 — diperiksa lebih dulu: pembekuan mengalahkan segalanya,
        // dan pesannya paling menjelaskan.
        if ($parent->status instanceof Withdrawn) {
            return ['parent_submission_id', 'Pengajuan ini sudah ditarik. Siklus monevnya dibekukan dan '
                .'tidak menerima laporan baru.'];
        }

        // BR-MON-01 — selama pengajuannya belum disetujui, tidak ada yang dipantau.
        if (!$parent->status instanceof Approved) {
            return ['parent_submission_id', 'Siklus monev terbuka setelah pengajuan disetujui.'];
        }

        $cycles = MonevCycles::details($parent);

        // W125 — hanya siklus milik induk ini; detail lain bukan laporannya.
        if (!$cycles->contains('id', $detail->id)) {
            return ['form_phase_detail_id', 'Tahap ini bukan siklus monev pengajuan tersebut.'];
        }

        $blocking = self::unfinishedPredecessor($parent, $cycles->takeWhile(fn (FormPhaseDetail $c): bool => $c->id !== $detail->id));

        // BR-MON-02 — urutan siklus: turunan keadaan lewat `canProceed()`, bukan kolom.
        return $blocking === null ? null : ['form_phase_detail_id', 'Selesaikan "'.MonevCycles::nameOf($blocking).'" lebih dulu — '
            .'tahapannya berurutan.'];
    }

    /**
     * BR-MON-02 — siklus sebelumnya yang belum tuntas, kalau ada. Siklus yang belum
     * pernah punya laporan menghalangi sama dengan yang laporannya belum disubmit.
     *
     * @param  iterable<FormPhaseDetail>  $predecessors
     */
    private static function unfinishedPredecessor(FormSubmission $parent, iterable $predecessors): ?FormPhaseDetail
    {
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
}
