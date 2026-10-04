<?php

namespace App\Actions\Period;

use App\Data\Request\SubmissionPeriodInput;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Merevisi nama, tahap, dan batas akhir periode dalam satu transaksi. Notifikasi
 * perpanjangan (BR-SCH-08) baru terkirim sesudah commit transaksi ini, sehingga
 * revisi yang gagal tidak pernah mengabari peneliti.
 *
 * @method static int run(SubmissionPeriod $period, SubmissionPeriodInput $input, User $actor)
 */
class ReviseSubmissionPeriod
{
    use AsAction;

    /** @return int jumlah peneliti yang akan diberi tahu */
    public function handle(SubmissionPeriod $period, SubmissionPeriodInput $input, User $actor): int
    {
        return DB::transaction(function () use ($period, $input, $actor): int {
            $old = [
                'name' => $period->name,
                'form_phase_ids' => $period->submissionPeriodPhases()->pluck('form_phase_id')->all(),
            ];

            $period->update(['name' => $input->name]);
            $period->submissionPeriodPhases()->delete();

            // Tanggal disinkronkan di antara dua penulisan tahap: galat sesudahnya
            // menggulung kembali semuanya, termasuk notifikasi yang tertunda.
            $notified = ExtendSubmissionDates::run($period, $input->dates, $actor);

            foreach ($input->formPhaseIds as $formPhaseId) {
                $period->submissionPeriodPhases()->create(['form_phase_id' => $formPhaseId]);
            }

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record(
                'Periode pengajuan direvisi',
                $period,
                $actor,
                new AuditChange(
                    old: $old,
                    new: ['name' => $input->name, 'form_phase_ids' => $input->formPhaseIds],
                ),
            );

            return $notified;
        });
    }
}
