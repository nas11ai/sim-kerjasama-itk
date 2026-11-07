<?php

namespace App\Actions\Monev;

use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Monev\MonevCycles;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\Support\AccountStateGuard;
use App\Support\Audit\AuditTrail;
use App\Support\MonevAccessGate;
use App\Support\PhaseAccessGate;
use App\Support\RequiredFieldGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * W121/W133 — mengirim laporan monev: DRAFT → SUBMITTED, tanpa event domain baru (DDD hanya
 * mengenal MonevStageOpened dan MonevEvaluationSubmitted) dan tanpa ProposalSubmitted.
 *
 * @method static FormSubmission run(FormSubmission $report, User $user)
 */
class SubmitMonevReport
{
    use AsAction;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(FormSubmission $report, User $user): FormSubmission
    {
        // BR-SM-04 — `submit` invarian (ADR 0015): Super Admin pun tidak mengirim atas nama ketua.
        if (!$user->can('submit', $report) || $report->is_submitted) {
            throw new AuthorizationException('Laporan ini terkunci.');
        }

        // W122 / BR-MON-08, lalu BR-MON-05.
        MonevAccessGate::assertReportOpen($report);
        PhaseAccessGate::assertOpenFor($report, $report->formPhaseDetail);

        // BR-IAM-04, lalu BR-SM-02 + BR-FE-11: akun lebih dulu, baru kelengkapan isian.
        AccountStateGuard::assertVerified($user);
        RequiredFieldGuard::assertComplete($report);

        $status = $report->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException("Laporan {$report->id} tidak punya status — tidak bisa disubmit.");
        }

        $report->update(['is_submitted' => true]);
        $status->transitionTo(Submitted::class);
        $report->refresh();

        // BR-RPT-09 — dibaca MonevCycles sebagai tanggal submit siklus.
        AuditTrail::record(MonevCycles::REPORT_SUBMITTED_AUDIT, $report, $user);

        return $report;
    }
}
