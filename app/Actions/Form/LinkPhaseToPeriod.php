<?php

namespace App\Actions\Form;

use App\Models\FormPhase;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\SubmissionPeriodGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menautkan tahap ke periode. Longgar (K9b): tenggat detail boleh dipindah sesudahnya;
 * gerbang pemilik tenggat ada di StartSubmission (BR-FE-17 / ADR 0014).
 *
 * @method static void run(FormPhase $phase, SubmissionPeriod $period, User $actor)
 */
class LinkPhaseToPeriod
{
    use AsAction;

    public function handle(FormPhase $phase, SubmissionPeriod $period, User $actor): void
    {
        $period->load('submissionDates');

        $alreadyLinked = $phase->submissionPeriodPhases()->where('submission_period_id', $period->id)->exists();

        if ($alreadyLinked || SubmissionPeriodGate::isFullyClosed($period)) {
            throw ValidationException::withMessages([
                'submission_period_id' => 'Periode ini sudah ditutup atau sudah terhubung dengan tahap ini.',
            ]);
        }

        DB::transaction(function () use ($phase, $period, $actor): void {
            $phase->submissionPeriodPhases()->create(['submission_period_id' => $period->id]);

            AuditTrail::record(
                'Tahap formulir dihubungkan ke periode',
                $phase,
                $actor,
                new AuditChange(new: ['submission_period_id' => $period->id]),
            );
        });
    }
}
