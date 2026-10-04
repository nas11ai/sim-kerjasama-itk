<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\Organization;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
            'formPhaseStatus' => FormPhaseStatusCounts::run(),
            'formPhaseTotal' => $this->total(),
            'formPhaseByPeriod' => $this->byPeriod(),
            ...OrganizationReferences::run(),
        ];
    }

    /** @return Collection<int, FormPhase> */
    private function byFaculty(): Collection
    {
        return FormPhase::select(
            'form_phases.id',
            'form_phases.title',
            'fac_org.id as faculty_id',
            'fac_org.name as faculty_name'
        )
            ->selectRaw('COUNT(DISTINCT facts.form_id) as total_forms')
            ->selectRaw('COUNT(DISTINCT facts.submission_id) as total_submissions')
            ->joinSub($this->facts($this->toFaculty(...)), 'facts', 'facts.phase_id', '=', 'form_phases.id')
            ->join('organizations as fac_org', 'fac_org.id', '=', 'facts.unit_id')
            ->groupBy('form_phases.id', 'form_phases.title', 'fac_org.id', 'fac_org.name')
            ->orderBy('fac_org.name')
            ->get();
    }

    /** @return Collection<int, FormPhase> */
    private function byStudyProgram(): Collection
    {
        return FormPhase::select(
            'form_phases.id',
            'form_phases.title',
            'fac_org.id as faculty_id',
            'fac_org.name as faculty_name',
            'sp_org.id as study_program_id',
            'sp_org.name as study_program_name'
        )
            ->selectRaw('COUNT(DISTINCT facts.form_id) as total_forms')
            ->selectRaw('COUNT(DISTINCT facts.submission_id) as total_submissions')
            ->joinSub($this->facts($this->toStudyProgram(...)), 'facts', 'facts.phase_id', '=', 'form_phases.id')
            ->join('organizations as sp_org', 'sp_org.id', '=', 'facts.unit_id')
            ->leftJoinSub(UnitFaculty::run(), 'unit_faculty', 'unit_faculty.unit_id', '=', 'sp_org.id')
            ->leftJoin('organizations as fac_org', 'fac_org.id', '=', 'unit_faculty.faculty_id')
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

    /** @return Collection<int, FormPhase> */
    private function total(): Collection
    {
        $query = FormPhase::select('form_phases.id', 'form_phases.title')
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions');

        return $this->withForms($query)
            ->leftJoin('form_submissions', $this->counted())
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
            ->leftJoin('form_submissions', $this->counted())
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
     * Baris (phase_id, unit_id, form_id, submission_id) untuk rincian per unit. Form ikut
     * unit kontrol aksesnya, sedangkan pengajuan ikut unit pengajunya (DDD 05_reporting):
     * kontrol akses tingkat fakultas tidak lagi muncul sebagai baris prodi.
     *
     * @param  Closure(QueryBuilder, string): QueryBuilder  $toUnit
     */
    private function facts(Closure $toUnit): QueryBuilder
    {
        $details = fn (): QueryBuilder => DB::table('form_phase_details')->join(
            'form_access_controls',
            'form_access_controls.id',
            '=',
            'form_phase_details.form_access_control_id',
        );

        $forms = $toUnit($details()->select(
            'form_phase_details.form_phase_id as phase_id',
            'form_access_controls.form_id',
            DB::raw('NULL::bigint as submission_id'),
        ), 'form_access_controls.organization_id');

        $submissions = $toUnit($details()
            ->join('form_submissions', $this->counted('form_access_controls.form_id'))
            ->join('user_profiles', 'user_profiles.user_id', '=', 'form_submissions.submitted_by')
            ->select(
                'form_phase_details.form_phase_id as phase_id',
                DB::raw('NULL::bigint as form_id'),
                'form_submissions.id as submission_id',
            ), 'user_profiles.organization_id');

        return $forms->unionAll($submissions);
    }

    private function toFaculty(QueryBuilder $query, string $organization): QueryBuilder
    {
        return $query
            ->joinSub(UnitFaculty::run(), 'unit_faculty', 'unit_faculty.unit_id', '=', $organization)
            ->addSelect('unit_faculty.faculty_id as unit_id');
    }

    private function toStudyProgram(QueryBuilder $query, string $organization): QueryBuilder
    {
        return $query
            ->join('organizations as unit_org', function (JoinClause $join) use ($organization): void {
                $join->on('unit_org.id', '=', $organization)->where('unit_org.type', 'study_program');
            })
            ->addSelect('unit_org.id as unit_id');
    }

    /** @return Closure(JoinClause): void */
    private function counted(string $form = 'forms.id'): Closure
    {
        $states = (new SubmissionStatusBuckets)->countedStates();

        return static function (JoinClause $join) use ($states, $form): void {
            $join->on('form_submissions.form_id', '=', $form)
                ->whereIn('form_submissions.status', $states);
        };
    }
}
