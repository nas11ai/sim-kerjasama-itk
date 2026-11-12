<?php

namespace App\Actions\Period;

use App\Data\Request\SubmissionPeriodInput;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Membuat periode pengajuan beserta batas akhir dan tahapnya dalam satu transaksi.
 *
 * @method static SubmissionPeriod run(SubmissionPeriodInput $input, User $actor)
 */
class CreateSubmissionPeriod
{
    use AsAction;

    public function handle(SubmissionPeriodInput $input, User $actor): SubmissionPeriod
    {
        return DB::transaction(function () use ($input, $actor): SubmissionPeriod {
            AssertOnePhasePerSubmissionForm::run($input->formPhaseIds, 'form_phase_ids');

            $period = SubmissionPeriod::create(['name' => $input->name]);

            foreach ($input->dates as $row) {
                SubmissionDate::create([
                    'submission_period_id' => $period->id,
                    'type' => $row->type,
                    'cycle' => $row->cycle,
                    'datetime' => $row->date,
                ]);
            }

            foreach ($input->formPhaseIds as $formPhaseId) {
                $period->submissionPeriodPhases()->create(['form_phase_id' => $formPhaseId]);
            }

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record(
                'Periode pengajuan dibuat',
                $period,
                $actor,
                new AuditChange(new: ['name' => $input->name, 'form_phase_ids' => $input->formPhaseIds]),
            );

            return $period;
        });
    }
}
