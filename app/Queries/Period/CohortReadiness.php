<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Period\CohortReadinessData;
use App\Models\FormPhaseDetail;
use App\Models\FormPhaseVersion;
use App\Models\PhaseVersionPin;
use App\Models\StepEvaluationForm;
use App\Models\SubmissionPeriodPhase;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-36 — kesiapan setiap angkatan periode `$periodIds` pada versi strukturnya (BR-FE-32).
 * Datanya dimuat sendiri dalam kueri tetap (G4-W7): relasi tersaring milik pemanggil tidak dipakai.
 *
 * @method static array<string, CohortReadinessData> run(list<int> $periodIds)
 */
class CohortReadiness implements ChangesNoState
{
    use AsAction;

    public static function key(int $periodId, int $phaseId): string
    {
        return "{$periodId}:{$phaseId}";
    }

    /**
     * @param  list<int>  $periodIds
     * @return array<string, CohortReadinessData> dikunci `key()`
     */
    public function handle(array $periodIds): array
    {
        $links = SubmissionPeriodPhase::query()
            ->whereIn('submission_period_id', $periodIds)
            ->with(['submissionPeriod.submissionDates', 'formPhase:id,title'])
            ->get();
        $pins = PhaseVersionPin::query()
            ->whereIn('submission_period_id', $periodIds)
            ->get()
            ->keyBy(fn (PhaseVersionPin $pin): string => self::key($pin->submission_period_id, $pin->form_phase_id));
        $versions = $this->cohortVersions($links, $pins);
        $steps = FormPhaseDetail::query()->whereIn('form_phase_version_id', $versions->modelKeys())->orderBy('order')->orderBy('id')->get();
        $withRequiredForm = $this->stepsWithRequiredForm($steps);
        $readiness = [];

        foreach ($links as $link) {
            if ($link->submissionPeriod === null || $link->formPhase === null) {
                continue;
            }

            $key = self::key($link->submission_period_id, $link->form_phase_id);
            $version = $versions->get($key);
            $readiness[$key] = new CohortReadinessData(
                periodId: $link->submission_period_id,
                phaseId: $link->form_phase_id,
                phaseTitle: $link->formPhase->title,
                versionLabel: $version?->label(),
                running: $pins->has($key),
                checks: (new CohortChecks(
                    $link->submissionPeriod,
                    array_values($steps->where('form_phase_version_id', $version?->id)->all()),
                    $withRequiredForm,
                ))->all(),
            );
        }

        return $readiness;
    }

    /**
     * BR-FE-32 — versi tiap angkatan: pakuan, atau versi terbaru tahapnya.
     *
     * @param  Collection<int, SubmissionPeriodPhase>  $links
     * @param  Collection<string, PhaseVersionPin>  $pins
     * @return Collection<string, FormPhaseVersion> dikunci `key()`
     */
    private function cohortVersions(Collection $links, Collection $pins): Collection
    {
        $all = FormPhaseVersion::query()
            ->whereIn('form_phase_id', $links->pluck('form_phase_id')->unique()->all())
            ->orderByDesc('number')
            ->get();
        $latest = $all->unique('form_phase_id')->keyBy('form_phase_id');
        $byId = $all->keyBy('id');
        $versions = new Collection;

        foreach ($links as $link) {
            $key = self::key($link->submission_period_id, $link->form_phase_id);
            $pinned = $pins->get($key)?->form_phase_version_id;
            $version = $pinned === null ? $latest->get($link->form_phase_id) : $byId->get($pinned);

            if ($version !== null) {
                $versions->put($key, $version);
            }
        }

        return $versions;
    }

    /**
     * Langkah yang punya Form Evaluasi wajib aktif (BR-FE-30), satu kueri untuk semua angkatan.
     *
     * @param  Collection<int, FormPhaseDetail>  $steps
     * @return array<int, true>
     */
    private function stepsWithRequiredForm(Collection $steps): array
    {
        return StepEvaluationForm::query()
            ->whereIn('form_phase_detail_id', $steps->modelKeys())
            ->active()
            ->required()
            ->get(['form_phase_detail_id'])
            ->mapWithKeys(fn (StepEvaluationForm $form): array => [$form->form_phase_detail_id => true])
            ->all();
    }
}
