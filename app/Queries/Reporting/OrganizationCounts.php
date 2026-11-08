<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\RankedCountData;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-06 — "Submission per Fakultas / Prodi" menurut unit **pengaju** (bukan kontrol akses
 * form) dan posisinya di pohon, aktif atau tidak. COUNT DISTINCT: tiap pengajuan sekali.
 *
 * @method static array{byFaculty: list<RankedCountData>, byStudyProgram: list<RankedCountData>} run(StatisticsFilters $filters)
 */
class OrganizationCounts implements ChangesNoState
{
    use AsAction;

    /** @return array{byFaculty: list<RankedCountData>, byStudyProgram: list<RankedCountData>} */
    public function handle(StatisticsFilters $filters): array
    {
        $faculty = $this->bySubmitter($filters)
            ->joinSub(UnitFaculty::run(), 'unit_faculty', 'unit_faculty.unit_id', '=', 'user_profiles.organization_id')
            ->join('organizations as unit', 'unit.id', '=', 'unit_faculty.faculty_id');
        $studyProgram = $this->bySubmitter($filters)
            ->join('organizations as unit', fn (JoinClause $join) => $join
                ->on('unit.id', '=', 'user_profiles.organization_id')
                ->where('unit.type', 'study_program'));

        return [
            'byFaculty' => $this->ranked($faculty),
            'byStudyProgram' => $this->ranked($studyProgram),
        ];
    }

    /** @return Builder<FormSubmission> */
    private function bySubmitter(StatisticsFilters $filters): Builder
    {
        return ReportingSubmissions::base($filters)
            ->join('user_profiles', 'user_profiles.user_id', '=', 'form_submissions.submitted_by');
    }

    /**
     * @param  Builder<FormSubmission>  $query  sudah ber-join alias `unit`
     * @return list<RankedCountData>
     */
    private function ranked(Builder $query): array
    {
        $rows = $query->toBase()
            ->select('unit.id', 'unit.name')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) AS total')
            ->groupBy('unit.id', 'unit.name')
            ->get();
        $ranked = [];

        foreach ($rows as $row) {
            $ranked[] = new RankedCountData(
                label: is_string($row->name) ? $row->name : '',
                count: is_numeric($row->total) ? (int) $row->total : 0,
            );
        }

        return RankedCountData::top($ranked);
    }
}
