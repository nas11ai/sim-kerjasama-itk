<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\SubmissionPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-06 — statistik pengajuan untuk layar statistik, dihitung langsung saat dibaca.
 * `totalByStatus` mengelompokkan state lewat SubmissionStatusBuckets dan hanya memuat
 * kelompok yang terisi; draft tidak dihitung.
 *
 * @phpstan-type SubmissionStats array{recentSubmissions: Collection<int, SubmissionPeriod>, totalSubmissions: Collection<int, SubmissionPeriod>, totalByStatus: list<array{status: string, total: int}>, totalByFaculty: Collection<int, FormSubmission>, totalByProdi: Collection<int, FormSubmission>, faculties: Collection<int, Organization>, studyPrograms: Collection<int, Organization>}
 *
 * @method static SubmissionStats run()
 */
class FormSubmissionStatistics implements ChangesNoState
{
    use AsAction;

    /** @return SubmissionStats */
    public function handle(): array
    {
        return [
            'recentSubmissions' => $this->periodsCreatedSince(now()->subHours(24)),
            'totalSubmissions' => $this->periodsCreatedSince(now()->subYears(1)),
            'totalByStatus' => $this->byStatus(),
            'totalByFaculty' => $this->byFaculty(),
            'totalByProdi' => $this->byStudyProgram(),
            ...OrganizationReferences::run(),
        ];
    }

    /** @return Collection<int, SubmissionPeriod> */
    private function periodsCreatedSince(CarbonInterface $since): Collection
    {
        return SubmissionPeriod::select('submission_periods.id', 'submission_periods.name')
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions')
            ->leftJoin('submission_period_phases', 'submission_period_phases.submission_period_id', '=', 'submission_periods.id')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'submission_period_phases.form_phase_id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('form_submissions', function (JoinClause $join) {
                $join->on('form_submissions.form_id', '=', 'forms.id')
                    ->where('form_submissions.is_submitted', '=', true);
            })
            ->where('submission_periods.created_at', '>=', $since)
            ->groupBy('submission_periods.id', 'submission_periods.name')
            ->get();
    }

    /** @return list<array{status: string, total: int}> */
    private function byStatus(): array
    {
        $rows = [];

        foreach (SubmissionStatusBuckets::run() as $bucket => $states) {
            $total = FormSubmission::whereIn('status', $states)->count();

            if ($total > 0) {
                $rows[] = ['status' => $bucket, 'total' => $total];
            }
        }

        return $rows;
    }

    /** @return Collection<int, FormSubmission> */
    private function byFaculty(): Collection
    {
        $query = FormSubmission::select('fac_org.id', 'fac_org.name', DB::raw('COUNT(DISTINCT form_submissions.id) as total'));

        return $this->inStudyPrograms($query)
            ->join('organizations as fac_org', 'fac_org.id', '=', 'sp_org.parent_id')
            ->groupBy('fac_org.id', 'fac_org.name')
            ->get();
    }

    /** @return Collection<int, FormSubmission> */
    private function byStudyProgram(): Collection
    {
        $query = FormSubmission::select('sp_org.id', 'sp_org.name', DB::raw('COUNT(DISTINCT form_submissions.id) as total'));

        return $this->inStudyPrograms($query)
            ->groupBy('sp_org.id', 'sp_org.name')
            ->get();
    }

    /**
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    private function inStudyPrograms(Builder $query): Builder
    {
        return $query
            ->join('forms', 'forms.id', '=', 'form_submissions.form_id')
            ->join('form_access_controls', 'form_access_controls.form_id', '=', 'forms.id')
            ->join('organizations as sp_org', function (JoinClause $join) {
                $join->on('sp_org.id', '=', 'form_access_controls.organization_id')
                    ->where('sp_org.type', 'study_program');
            });
    }
}
