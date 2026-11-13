<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\CohortEvaluationVersionData;
use App\Data\View\FormEngine\PastDeadlineSubmissionData;
use App\Models\EvaluationFormPin;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewFormResponse;
use App\Models\StepEvaluationForm;
use App\Models\SubmissionPeriod;
use App\Queries\Form\PhaseVersionFor;
use App\Queries\Review\DecidedSubmissionsAtStep;
use App\Queries\Review\SubmissionsPastEvaluationDeadline;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-18, BR-REV-19 — layar Versi Formulir Evaluasi satu periode (OPERATOR-09-01): tiap Form
 * Evaluasi aktif di langkah direview milik fase tertaut, dengan versi pakuan angkatannya, versi
 * terbaru, dan apakah versi terbaru boleh diterapkan.
 *
 * @method static list<CohortEvaluationVersionData> run(SubmissionPeriod $period)
 */
class CohortEvaluationVersions implements ChangesNoState
{
    use AsAction;

    /** @var array<int, int> jumlah pengajuan diputuskan per langkah, dihitung sekali */
    private array $decided = [];

    /** @var array<string, int> jawaban berlaku per "langkah:form evaluasi", satu kueri untuk semua baris */
    private array $responses = [];

    /** @return list<CohortEvaluationVersionData> */
    public function handle(SubmissionPeriod $period): array
    {
        $pins = EvaluationFormPin::query()
            ->where('submission_period_id', $period->id)
            ->with('reviewEvaluationFormVersion:id,number')
            ->get()
            ->keyBy(fn (EvaluationFormPin $pin): string => "{$pin->form_phase_detail_id}:{$pin->review_evaluation_form_id}");
        $this->responses = $this->responseCounts($period);

        return array_values(StepEvaluationForm::query()
            ->active()
            ->whereHas('formPhaseDetail', fn (Builder $detail) => $detail
                ->where('needs_review', true)
                ->whereIn('form_phase_version_id', $this->cohortVersionIds($period)))
            ->with(['formPhaseDetail.formPhase:id,title', 'reviewEvaluationForm.latestVersion'])
            ->get()
            ->sortBy([
                fn (StepEvaluationForm $a, StepEvaluationForm $b): int => [$a->formPhaseDetail->formPhase->title ?? '', $a->formPhaseDetail->form_phase_id]
                    <=> [$b->formPhaseDetail->formPhase->title ?? '', $b->formPhaseDetail->form_phase_id],
                fn (StepEvaluationForm $a, StepEvaluationForm $b): int => [$a->formPhaseDetail->order, $a->order] <=> [$b->formPhaseDetail->order, $b->order],
            ])
            ->map(fn (StepEvaluationForm $step): CohortEvaluationVersionData => $this->row(
                $period,
                $step,
                $pins->get("{$step->form_phase_detail_id}:{$step->review_evaluation_form_id}"),
            ))
            ->all());
    }

    /**
     * BR-FE-32 — versi struktur tiap angkatan periode ini: pakuan, atau terbaru tahapnya.
     *
     * @return list<int>
     */
    private function cohortVersionIds(SubmissionPeriod $period): array
    {
        $ids = [];

        foreach (FormPhase::query()->whereIn('id', $period->submissionPeriodPhases()->select('form_phase_id'))->get() as $phase) {
            $version = PhaseVersionFor::run($period, $phase);

            if ($version !== null) {
                $ids[] = $version->id;
            }
        }

        return $ids;
    }

    private function row(SubmissionPeriod $period, StepEvaluationForm $step, ?EvaluationFormPin $pin): CohortEvaluationVersionData
    {
        $detail = $step->formPhaseDetail;
        $latest = $step->reviewEvaluationForm->latestVersion;
        $decided = $this->decidedAt($period, $detail);

        return new CohortEvaluationVersionData(
            phaseTitle: $detail->formPhase->title ?? '',
            stepLabel: $detail->deadline_type->stepLabel($detail->deadline_cycle),
            formTitle: $step->reviewEvaluationForm->title,
            pinId: $pin?->id,
            pinnedLabel: $pin?->reviewEvaluationFormVersion->label(),
            // G2-W3 — hanya Formulir Evaluasi yang sudah terbit bisa dipasang, jadi versi terbarunya ada.
            latestLabel: $latest?->label() ?? '',
            responseCount: $this->responses["{$detail->id}:{$step->review_evaluation_form_id}"] ?? 0,
            decidedCount: $decided,
            // BR-REV-19 — hanya angkatan yang terpaku ke versi lama dan belum ada yang diputuskan.
            canApply: $pin !== null && $latest !== null && $pin->review_evaluation_form_version_id !== $latest->id && $decided === 0,
            pastDeadline: $pin === null ? [] : $this->pastDeadline($pin),
        );
    }

    /**
     * K-3 (b) — Apply tidak menggeser tenggat; layar menyebut pengajuan yang butuh perpanjangan.
     *
     * @return list<PastDeadlineSubmissionData>
     */
    private function pastDeadline(EvaluationFormPin $pin): array
    {
        return array_values(SubmissionsPastEvaluationDeadline::run($pin)
            ->map(fn (FormSubmission $submission): PastDeadlineSubmissionData => new PastDeadlineSubmissionData(
                $submission->id,
                $submission->submittedBy->name,
            ))
            ->all());
    }

    /**
     * B10 — padanan berkelompok `ReviewFormResponse::currentInCohortOf()` untuk seluruh periode.
     *
     * @return array<string, int>
     */
    private function responseCounts(SubmissionPeriod $period): array
    {
        $counts = [];
        $rows = ReviewFormResponse::query()
            ->join('reviewer_form_assignments as rfa', 'rfa.id', '=', 'review_form_responses.reviewer_form_assignment_id')
            ->join('submission_reviewers as seat', 'seat.id', '=', 'rfa.submission_reviewer_id')
            ->join('form_submissions as fs', 'fs.id', '=', 'seat.form_submission_id')
            ->whereNull('review_form_responses.superseded_at')
            ->where('seat.status', 'active')
            ->where('fs.submission_period_id', $period->id)
            ->groupBy('fs.form_phase_detail_id', 'rfa.review_evaluation_form_id')
            ->selectRaw("CONCAT(fs.form_phase_detail_id, ':', rfa.review_evaluation_form_id) AS cohort_key, COUNT(*) AS total")
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->cohort_key)) {
                $counts[$row->cohort_key] = is_numeric($row->total) ? (int) $row->total : 0;
            }
        }

        return $counts;
    }

    private function decidedAt(SubmissionPeriod $period, FormPhaseDetail $detail): int
    {
        return $this->decided[$detail->id] ??= DecidedSubmissionsAtStep::run($period, $detail);
    }
}
