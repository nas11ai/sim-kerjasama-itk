<?php

namespace App\Actions\Period;

use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Period\SubmissionPeriodReferences;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-21 — menghapus periode pengajuan beserta tanggal dan tahapnya.
 *
 * Dua penghalang, bukan satu. Pengajuan yang merujuk periode sudah jelas;
 * yang kedua lebih halus — BR-FE-07 mewajibkan setiap FormPhaseDetail punya
 * `submission_date_id`, jadi menghapus tanggal milik periode ini membuat
 * aturan yang seluruh Form Engine andalkan berhenti terpenuhi, di tempat yang
 * jauh dari sini.
 *
 * @method static void run(SubmissionPeriod $period, User $actor)
 */
class DeleteSubmissionPeriod
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(SubmissionPeriod $period, User $actor): void
    {
        ReferenceGuard::assertUnreferenced('Periode pengajuan', SubmissionPeriodReferences::run($period));

        AuditTrail::record(
            'Periode pengajuan dihapus',
            $period,
            $actor,
            new AuditChange(
                old: ['name' => $period->name],
            ),
        );

        DB::transaction(function () use ($period): void {
            $period->submissionDates()->delete();
            $period->submissionPeriodPhases()->delete();
            $period->delete();
        });
    }
}
