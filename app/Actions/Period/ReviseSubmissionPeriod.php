<?php

namespace App\Actions\Period;

use App\Actions\Form\AssertPhasesPublished;
use App\Data\Request\SubmissionPeriodInput;
use App\Data\Result\DeadlineExtension;
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
 * @method static DeadlineExtension run(SubmissionPeriod $period, SubmissionPeriodInput $input, User $actor)
 */
class ReviseSubmissionPeriod
{
    use AsAction;

    public function handle(SubmissionPeriod $period, SubmissionPeriodInput $input, User $actor): DeadlineExtension
    {
        return DB::transaction(function () use ($period, $input, $actor): DeadlineExtension {
            // BR-FE-29 — sama dengan LinkPhaseToPeriod: kunci baris periode, lalu periksa daftar baru.
            SubmissionPeriod::query()->whereKey($period->id)->lockForUpdate()->first();
            AssertPhasesPublished::run($input->formPhaseIds, 'form_phase_ids');
            AssertOnePhasePerSubmissionForm::run($input->formPhaseIds, 'form_phase_ids');

            $old = [
                'name' => $period->name,
                'form_phase_ids' => $period->submissionPeriodPhases()->pluck('form_phase_id')->all(),
            ];

            $period->update(['name' => $input->name]);
            $period->submissionPeriodPhases()->delete();

            foreach ($input->formPhaseIds as $formPhaseId) {
                $period->submissionPeriodPhases()->create(['form_phase_id' => $formPhaseId]);
            }

            // BR-FE-26 (ADR 0019) — tanggal disinkronkan sesudah tautan tahap: tanggal yang dibaca
            // langkah tertaut tetap. Galat sesudahnya menggulung semuanya, termasuk notifikasi.
            $extension = ExtendSubmissionDates::run($period, $input->dates, $actor);

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

            return $extension;
        });
    }
}
