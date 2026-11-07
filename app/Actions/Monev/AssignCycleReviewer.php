<?php

namespace App\Actions\Monev;

use App\Actions\FormSubmission\StartChildSubmission;
use App\Actions\Review\AssignReviewer;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-MON-04, BR-MON-06 — Operator menugaskan satu reviewer pada satu siklus monev. Laporannya
 * dipastikan ada lebih dulu, supaya penugasan bisa dilakukan sebelum siklus diaktifkan (BR-MON-10).
 *
 * @method static SubmissionReviewer run(FormSubmission $parent, FormPhaseDetail $detail, Reviewer $reviewer, User $operator)
 */
class AssignCycleReviewer
{
    use AsAction;

    public function handle(FormSubmission $parent, FormPhaseDetail $detail, Reviewer $reviewer, User $operator): SubmissionReviewer
    {
        // Penugasan yang ditolak tidak meninggalkan laporan kosong yang baru dibuat.
        return DB::transaction(function () use ($parent, $detail, $reviewer, $operator): SubmissionReviewer {
            $report = StartChildSubmission::run($parent, $detail, $operator);

            // BR-MON-06 — baris baru per laporan. BR-REV-02/W124 — konflik tim induk ditolak guard-nya.
            // BR-MON-07 — saran tidak menyaring: siapa pun yang lolos guard boleh dipilih.
            $seat = AssignReviewer::run($report, $reviewer);

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record('Reviewer siklus monev ditugaskan', $report, $operator);

            return $seat;
        });
    }
}
