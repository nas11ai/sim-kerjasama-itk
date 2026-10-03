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
 * Dua penghalang: pengajuan yang merujuk periode, dan detail tahap yang
 * memakai tanggalnya, yang halus — BR-FE-07, di SubmissionPeriodReferences.
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
        // Audit dicatat sebelum delete() memicu hook; tanpa guard sendiri,
        // penghapusan yang ditolak meninggalkan entri audit palsu.
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
