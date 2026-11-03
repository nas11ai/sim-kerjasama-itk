<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\PhaseCardData;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriodPhase;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Kartu tahap tab Phases (ADMIN-08-08): tahap yang detailnya memakai aturan akses Form ini
 * (W92), urut id. Semua hitungan lewat subquery, tanpa kueri per kartu.
 *
 * @method static list<PhaseCardData> run(Form $form)
 */
class FormPhaseCards implements ChangesNoState
{
    use AsAction;

    /** @return list<PhaseCardData> */
    public function handle(Form $form): array
    {
        $phases = FormPhase::query()
            ->usingForm($form)
            ->withCount('formPhaseDetails')
            ->addSelect(['submission_count' => FormSubmission::query()
                ->selectRaw('count(*)')
                ->join('form_phase_details', 'form_phase_details.id', '=', 'form_submissions.form_phase_detail_id')
                ->whereColumn('form_phase_details.form_phase_id', 'form_phases.id'),
            ])
            ->orderBy('form_phases.id')
            ->get();

        $periods = $this->periodNames($phases);

        return array_values($phases->map(fn (FormPhase $phase): PhaseCardData => new PhaseCardData(
            id: $phase->id,
            title: $phase->title,
            description: $phase->description,
            isActive: $phase->is_active,
            detailCount: $phase->form_phase_details_count,
            periodNames: $periods[$phase->id] ?? [],
            submissionCount: $phase->submission_count,
            linkablePeriods: [],
            deadlineGaps: [],
        ))->all());
    }

    /**
     * @param  Collection<int, FormPhase>  $phases
     * @return array<int, list<string>>
     */
    private function periodNames(Collection $phases): array
    {
        $names = [];
        $links = SubmissionPeriodPhase::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->with('submissionPeriod:id,name')
            ->get();

        foreach ($links as $link) {
            $names[$link->form_phase_id][] = $link->submissionPeriod->name ?? '';
        }

        return array_map(function (array $list): array {
            sort($list);

            return $list;
        }, $names);
    }
}
