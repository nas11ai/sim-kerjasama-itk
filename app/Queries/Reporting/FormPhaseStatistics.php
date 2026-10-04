<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use stdClass;

/**
 * BR-RPT-06 — statistik fase form untuk layar statistik, dihitung langsung saat dibaca.
 * Rincian status memakai SubmissionStatusBuckets, jadi draft tidak ikut terhitung.
 *
 * @phpstan-type PhaseStats array{formPhaseFaculty: Collection<int, FormPhase>, formPhaseProdi: Collection<int, FormPhase>, formPhaseStatus: BaseCollection<int, stdClass>, formPhaseTotal: Collection<int, FormPhase>, formPhaseByPeriod: Collection<int, FormPhase>, faculties: Collection<int, Organization>, studyPrograms: Collection<int, Organization>}
 *
 * @method static PhaseStats run()
 */
class FormPhaseStatistics implements ChangesNoState
{
    use AsAction;

    /** @return PhaseStats */
    public function handle(): array
    {
        return [
            'formPhaseFaculty' => $this->byFaculty(),
            'formPhaseProdi' => $this->byStudyProgram(),
            'formPhaseStatus' => $this->byStatus(),
            'formPhaseTotal' => $this->total(),
            'formPhaseByPeriod' => $this->byPeriod(),
            ...OrganizationReferences::run(),
        ];
    }

    /** @return Collection<int, FormPhase> */
    private function byFaculty(): Collection
    {
        $query = FormPhase::select(
            'form_phases.id',
            'form_phases.title',
            'fac_org.id as faculty_id',
            'fac_org.name as faculty_name'
        );

        return $this->withSubmittedCounts($query)
            ->groupBy('form_phases.id', 'form_phases.title', 'fac_org.id', 'fac_org.name')
            ->orderBy('fac_org.name')
            ->get();
    }

    /** @return Collection<int, FormPhase> */
    private function byStudyProgram(): Collection
    {
        $query = FormPhase::select(
            'form_phases.id',
            'form_phases.title',
            'fac_org.id as faculty_id',
            'fac_org.name as faculty_name',
            'sp_org.id as study_program_id',
            'sp_org.name as study_program_name'
        );

        return $this->withSubmittedCounts($query)
            ->groupBy(
                'form_phases.id',
                'form_phases.title',
                'fac_org.id',
                'fac_org.name',
                'sp_org.id',
                'sp_org.name'
            )
            ->orderBy('fac_org.name')
            ->orderBy('sp_org.name')
            ->get();
    }

    /** @return BaseCollection<int, stdClass> */
    private function byStatus(): BaseCollection
    {
        // Subquery DISTINCT supaya satu pengajuan tidak terhitung dua kali bila form-nya
        // terpasang di beberapa akses; draft dikeluarkan lewat daftar state yang dihitung.
        $buckets = SubmissionStatusBuckets::run();
        $query = DB::query()->fromRaw('(
            SELECT DISTINCT
                form_phases.id AS phase_id,
                form_phases.title AS phase_title,
                form_submissions.id AS submission_id,
                form_submissions.status
            FROM form_phases
            LEFT JOIN form_phase_details ON form_phase_details.form_phase_id = form_phases.id
            LEFT JOIN form_access_controls ON form_access_controls.id = form_phase_details.form_access_control_id
            LEFT JOIN forms ON forms.id = form_access_controls.form_id
            LEFT JOIN form_submissions ON form_submissions.form_id = forms.id
        ) as distinct_subs')
            ->select('phase_id as id', 'phase_title as title')
            ->selectRaw("COUNT(submission_id) FILTER (WHERE status = ANY (string_to_array(?, ','))) as total_submissions", [$this->stateList((new SubmissionStatusBuckets)->countedStates())])
            ->selectRaw("COUNT(submission_id) FILTER (WHERE status = ANY (string_to_array(?, ','))) as pending", [$this->stateList($buckets['pending'])])
            ->selectRaw("COUNT(submission_id) FILTER (WHERE status = ANY (string_to_array(?, ','))) as under_review", [$this->stateList($buckets['under_review'])])
            ->selectRaw("COUNT(submission_id) FILTER (WHERE status = ANY (string_to_array(?, ','))) as approved", [$this->stateList($buckets['approved'])])
            ->selectRaw("COUNT(submission_id) FILTER (WHERE status = ANY (string_to_array(?, ','))) as rejected", [$this->stateList($buckets['rejected'])])
            ->selectRaw("COUNT(submission_id) FILTER (WHERE status = ANY (string_to_array(?, ','))) as revision", [$this->stateList($buckets['revision'])])
            ->selectRaw("COUNT(submission_id) FILTER (WHERE status = ANY (string_to_array(?, ','))) as withdrawn", [$this->stateList($buckets['withdrawn'])]);

        return $query->groupBy('phase_id', 'phase_title')->get();
    }

    /** @return Collection<int, FormPhase> */
    private function total(): Collection
    {
        $query = FormPhase::select('form_phases.id', 'form_phases.title')
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions');

        return $this->withForms($query)
            ->leftJoin('form_submissions', 'form_submissions.form_id', '=', 'forms.id')
            ->groupBy('form_phases.id', 'form_phases.title')
            ->get();
    }

    /** @return Collection<int, FormPhase> */
    private function byPeriod(): Collection
    {
        $query = FormPhase::select(
            'submission_periods.id as submission_period_id',
            'submission_periods.name as submission_period_name'
        )
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions')
            ->leftJoin('submission_period_phases', 'submission_period_phases.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('submission_periods', 'submission_periods.id', '=', 'submission_period_phases.submission_period_id');

        return $this->withForms($query)
            ->leftJoin('form_submissions', 'form_submissions.form_id', '=', 'forms.id')
            ->groupBy('submission_periods.id', 'submission_periods.name')
            ->get();
    }

    /**
     * @param  Builder<FormPhase>  $query
     * @return Builder<FormPhase>
     */
    private function withForms(Builder $query): Builder
    {
        return $query
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id');
    }

    /**
     * @param  Builder<FormPhase>  $query
     * @return Builder<FormPhase>
     */
    private function withSubmittedCounts(Builder $query): Builder
    {
        return $this->withForms($query)
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions')
            ->leftJoin('organizations as sp_org', 'sp_org.id', '=', 'form_access_controls.organization_id')
            ->leftJoin('organizations as fac_org', 'fac_org.id', '=', 'sp_org.parent_id')
            ->leftJoin('form_submissions', function (JoinClause $join) {
                $join->on('form_submissions.form_id', '=', 'forms.id')
                    ->where('form_submissions.is_submitted', true);
            });
    }

    /** @param list<string> $states */
    private function stateList(array $states): string
    {
        return implode(',', $states);
    }
}
