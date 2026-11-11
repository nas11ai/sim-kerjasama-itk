<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\PhaseCardData;
use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriodPhase;
use App\Queries\Period\PeriodScheduleGaps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pustaka fase (ADMIN-08-08): semua tahap urut judul lalu id (R2-W5); `$usingForm` menapis tahap
 * yang langkah mana pun memakai Form itu. Semua hitungan lewat subquery, tanpa kueri per kartu.
 *
 * @method static list<PhaseCardData> run(?Form $usingForm = null)
 */
class PhaseLibrary implements ChangesNoState
{
    use AsAction;

    /** @return list<PhaseCardData> */
    public function handle(?Form $usingForm = null): array
    {
        $phases = FormPhase::query()
            ->when($usingForm, fn ($query, Form $form) => $query->usingForm($form))
            ->withCount('formPhaseDetails')
            ->addSelect(['submission_count' => FormSubmission::query()
                ->selectRaw('count(*)')
                ->join('form_phase_details', 'form_phase_details.id', '=', 'form_submissions.form_phase_detail_id')
                ->whereColumn('form_phase_details.form_phase_id', 'form_phases.id'),
            ])
            ->addSelect(['submission_form_title' => $this->submissionFormTitle()])
            ->orderBy('form_phases.title')
            ->orderBy('form_phases.id')
            ->get();

        $periods = $this->periodNames($phases);
        $linkable = LinkablePeriods::forPhases($phases);
        $gaps = PeriodScheduleGaps::forPhases($phases);

        return array_values($phases->map(fn (FormPhase $phase): PhaseCardData => new PhaseCardData(
            id: $phase->id,
            title: $phase->title,
            description: $phase->description,
            isActive: $phase->is_active,
            detailCount: $phase->form_phase_details_count,
            periodNames: $periods[$phase->id] ?? [],
            submissionCount: $phase->submission_count,
            linkablePeriods: $linkable[$phase->id] ?? [],
            deadlineGaps: $gaps[$phase->id] ?? [],
            submissionFormTitle: $phase->submission_form_title,
        ))->all());
    }

    /**
     * BR-FE-28 — judul Form langkah Pengajuan (`FormPhaseDetail::opensSubmission()`); paling banyak
     * satu Form per tahap, tetapi bisa lewat beberapa aturan akses, jadi baris pertama cukup.
     *
     * @return Builder<Form>
     */
    private function submissionFormTitle(): Builder
    {
        return Form::query()
            ->select('forms.title')
            ->join('form_access_controls', 'form_access_controls.form_id', '=', 'forms.id')
            ->join('form_phase_details', 'form_phase_details.form_access_control_id', '=', 'form_access_controls.id')
            ->whereColumn('form_phase_details.form_phase_id', 'form_phases.id')
            ->where('form_phase_details.deadline_type', DeadlineType::Submit)
            ->whereNull('form_phase_details.deadline_cycle')
            ->orderBy('form_phase_details.order')
            ->orderBy('form_phase_details.id')
            ->limit(1);
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
