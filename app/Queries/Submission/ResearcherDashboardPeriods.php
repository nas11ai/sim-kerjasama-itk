<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\SubmissionPeriodGate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Periode di dasbor peneliti dengan kemajuan per fase yang terbuka baginya. Yang dihitung baris yang juga
 * ditulis wizard (ResolveWizardSubmission): pengajuan induk peneliti untuk form itu **di periode itu**.
 * Dulu dicari per form saja, jadi form yang dipakai ulang lintas periode menandai periode lain selesai.
 *
 * @phpstan-type Progress array{total_forms: int, completed_forms: int, pending_review: int, can_proceed: bool}
 * @phpstan-type PhaseRow array{id: int, title: string, description: string|null, user_can_access: true, user_progress: Progress}
 * @phpstan-type PeriodRow array{id: int, name: string, is_active: bool, status: string, start_date: CarbonInterface|null, end_date: CarbonInterface|null, submission_dates: Collection<int, SubmissionDate>, form_phases: list<PhaseRow>}
 *
 * @method static list<PeriodRow> run(User $user)
 */
class ResearcherDashboardPeriods implements ChangesNoState
{
    use AsAction;

    /** @return list<PeriodRow> */
    public function handle(User $user): array
    {
        $accessible = FormAccessControl::query()->accessibleBy($user)->select('id');

        $periods = SubmissionPeriod::query()
            ->with([
                'submissionDates.submissionDateLabel',
                'submissionPeriodPhases.formPhase.formPhaseDetails' => fn (Relation $q) => $q
                    ->whereIn('form_access_control_id', $accessible)
                    ->with('formAccessControl:id,form_id'),
            ])
            ->get();

        // Urut naik lalu keyBy: yang tersisa per kunci adalah id terbesar, sama dengan resolver.
        $mine = FormSubmission::query()
            ->whereNull('parent_submission_id')
            ->where('submitted_by', $user->id)
            ->orderBy('id')
            ->get()
            ->keyBy(fn (FormSubmission $submission): string => "{$submission->submission_period_id}:{$submission->form_id}");

        return array_values($periods->map(fn (SubmissionPeriod $period): array => $this->row($period, $mine))->all());
    }

    /**
     * @param  Collection<string, FormSubmission>  $mine
     * @return PeriodRow
     */
    private function row(SubmissionPeriod $period, Collection $mine): array
    {
        $dates = $period->submissionDates->sortBy('datetime');
        $phases = [];

        foreach ($period->submissionPeriodPhases as $periodPhase) {
            $row = $this->phaseRow($periodPhase->formPhase, $period, $mine);

            if ($row !== null) {
                $phases[] = $row;
            }
        }

        return [
            'id' => $period->id,
            'name' => $period->name,
            // BR-FE-01 — keaktifan periode dihitung satu tempat (SubmissionPeriodGate), yang juga menggerbangi
            // pembuatan pengajuan. Salinan yang dulu di sini melupakan is_force_closed, jadi periode yang
            // ditutup Operator tampak masih terbuka lalu menolak peneliti saat mencoba.
            'is_active' => SubmissionPeriodGate::isOpen($period),
            'status' => SubmissionPeriodGate::displayStatus($period),
            'start_date' => $dates->first()?->datetime,
            'end_date' => $dates->last()?->datetime,
            'submission_dates' => $period->submissionDates,
            'form_phases' => $phases,
        ];
    }

    /**
     * Fase tanpa satu pun form yang terbuka bagi peneliti tidak ditampilkan.
     *
     * @param  Collection<string, FormSubmission>  $mine
     * @return PhaseRow|null
     */
    private function phaseRow(?FormPhase $phase, SubmissionPeriod $period, Collection $mine): ?array
    {
        if ($phase === null) {
            return null;
        }

        $progress = $this->progress($phase, $period, $mine);

        if ($progress['total_forms'] === 0) {
            return null;
        }

        return [
            'id' => $phase->id,
            'title' => $phase->title,
            'description' => $phase->description,
            'user_can_access' => true,
            'user_progress' => $progress,
        ];
    }

    /**
     * Satu form bisa terbuka lewat dua kontrol akses (prodi dan fakultasnya, ADR 0013). Wizard
     * menampilkannya sebagai satu langkah, jadi di sini juga dihitung sekali.
     *
     * @param  Collection<string, FormSubmission>  $mine
     * @return Progress
     */
    private function progress(FormPhase $phase, SubmissionPeriod $period, Collection $mine): array
    {
        $forms = $phase->formPhaseDetails
            ->filter(fn (FormPhaseDetail $detail): bool => $detail->formAccessControl !== null)
            ->groupBy(fn (FormPhaseDetail $detail): int => $detail->formAccessControl->form_id ?? 0);
        $completed = 0;
        $pending = 0;

        foreach ($forms as $formId => $details) {
            $submission = $mine->get("{$period->id}:{$formId}");

            if ($submission === null || !$submission->is_submitted) {
                continue;
            }

            $completed++;

            if ($details->contains(fn (FormPhaseDetail $detail): bool => $detail->needs_review) && !$submission->canProceed()) {
                $pending++;
            }
        }

        return [
            'total_forms' => $forms->count(),
            'completed_forms' => $completed,
            'pending_review' => $pending,
            'can_proceed' => $pending === 0,
        ];
    }
}
