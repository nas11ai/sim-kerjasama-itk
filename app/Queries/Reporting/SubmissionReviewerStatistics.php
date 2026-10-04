<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Organization;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-06 — statistik reviewer untuk layar statistik, dihitung langsung saat dibaca.
 *
 * @phpstan-type ReviewerStats array{reviewerRecent: Collection<int, Reviewer>, totalReviewers: int, totalByRole: Collection<int, Reviewer>, evaluationStatus: Collection<int, SubmissionReviewer>, reviewerByYear: Collection<int, Reviewer>, reviewerByFaculty: Collection<int, Reviewer>, reviewerByProdi: Collection<int, Reviewer>, reviewerActiveStatus: Collection<int, Reviewer>, faculties: Collection<int, Organization>, studyPrograms: Collection<int, Organization>}
 *
 * @method static ReviewerStats run()
 */
class SubmissionReviewerStatistics implements ChangesNoState
{
    use AsAction;

    /** @return ReviewerStats */
    public function handle(): array
    {
        return [
            'reviewerRecent' => $this->recent(),
            'totalReviewers' => Reviewer::count(),
            'totalByRole' => Reviewer::select('reviewer_type')
                ->selectRaw('COUNT(id) as total_reviewers')
                ->groupBy('reviewer_type')
                ->get(),
            'evaluationStatus' => SubmissionReviewer::select('evaluation_status', DB::raw('count(*) as total'))
                ->groupBy('evaluation_status')
                ->get(),
            'reviewerByYear' => Reviewer::select(DB::raw('EXTRACT(YEAR FROM created_at) as year'), DB::raw('count(*) as total'))
                ->groupByRaw('EXTRACT(YEAR FROM created_at)')
                ->get(),
            'reviewerByFaculty' => $this->byFaculty(),
            'reviewerByProdi' => $this->byStudyProgram(),
            'reviewerActiveStatus' => Reviewer::select('user_id', 'reviewer_type')
                ->groupBy('user_id', 'reviewer_type')
                ->get(),
            ...OrganizationReferences::run(),
        ];
    }

    /** @return Collection<int, Reviewer> */
    private function recent(): Collection
    {
        return Reviewer::where('reviewers.created_at', '>=', now()->subHours(24))
            ->leftJoin('users', 'users.id', '=', 'reviewers.user_id')
            ->select(
                'reviewers.id as id',
                'reviewers.user_id as user_id',
                'users.name as users_name',
                'reviewers.reviewer_type'
            )
            ->orderBy('reviewers.id')
            ->get();
    }

    /** @return Collection<int, Reviewer> */
    private function byFaculty(): Collection
    {
        return Reviewer::select('parent_org.id', 'parent_org.name', DB::raw('count(*) as total'))
            ->join('user_profiles', 'user_profiles.user_id', '=', 'reviewers.user_id')
            ->join('organizations', 'organizations.id', '=', 'user_profiles.organization_id')
            ->join('organizations as parent_org', 'parent_org.id', '=', 'organizations.parent_id')
            ->where('parent_org.type', 'faculty')
            ->groupBy('parent_org.id', 'parent_org.name')
            ->get();
    }

    /** @return Collection<int, Reviewer> */
    private function byStudyProgram(): Collection
    {
        return Reviewer::select('organizations.id', 'organizations.name', DB::raw('count(*) as total'))
            ->join('user_profiles', 'user_profiles.user_id', '=', 'reviewers.user_id')
            ->join('organizations', 'organizations.id', '=', 'user_profiles.organization_id')
            ->where('organizations.type', 'study_program')
            ->groupBy('organizations.id', 'organizations.name')
            ->get();
    }
}
