<?php

namespace App\Http\Controllers;

use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\Reviewer;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Reporting\SubmissionStatusBuckets;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StatController extends Controller
{
    /** @param list<string> $states */
    private function bucketCount(string $bucket, array $states): string
    {
        $states = implode("','", $states);

        return "COUNT(submission_id) FILTER (WHERE status IN ('{$states}')) as {$bucket}";
    }

    private function getAllStats()
    {
        return [
            'formPhase' => $this->getFormPhaseStats(),
            'formSubmission' => $this->getFormSubmissionStats(),
            'user' => $this->getUserStats(),
            'submissionReviewer' => $this->getSubmissionReviewerStats(),
        ];
    }

    public function dashboard()
    {
        return Inertia::render('DashboardPage', $this->getAllStats());
    }

    // form phase Index
    public function formPhaseStatIndex()
    {
        return Inertia::render(
            'Statistics/FormPhaseStats',
            $this->getFormPhaseStats(),
        );
    }

    // form submission Index
    public function formSubmissionStatIndex()
    {
        return Inertia::render(
            'Statistics/FormSubmissionStats',
            $this->getFormSubmissionStats(),
        );
    }

    // submission reviewer Index
    public function submissionReviewerStatIndex()
    {
        return Inertia::render(
            'Statistics/SubmissionReviewerStats',
            $this->getSubmissionReviewerStats()
        );
    }

    // user Index
    public function userStatIndex()
    {
        return Inertia::render(
            'Statistics/UserStats',
            $this->getUserStats(),
        );
    }

    // get form phase Data
    public function getFormPhaseStats()
    {
        // === 1. Fakultas ===
        $formPhaseFaculty = FormPhase::select(
            'form_phases.id',
            'form_phases.title',
            'fac_org.id as faculty_id',
            'fac_org.name as faculty_name'
        )
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('organizations as sp_org', 'sp_org.id', '=', 'form_access_controls.organization_id')
            ->leftJoin('organizations as fac_org', 'fac_org.id', '=', 'sp_org.parent_id')
            ->leftJoin('form_submissions', function ($join) {
                $join->on('form_submissions.form_id', '=', 'forms.id')
                    ->where('form_submissions.is_submitted', true);
            })
            ->groupBy('form_phases.id', 'form_phases.title', 'fac_org.id', 'fac_org.name')
            ->orderBy('fac_org.name')
            ->get();

        // === 2. Program Studi ===
        $formPhaseProdi = FormPhase::select(
            'form_phases.id',
            'form_phases.title',
            'fac_org.id as faculty_id',
            'fac_org.name as faculty_name',
            'sp_org.id as study_program_id',
            'sp_org.name as study_program_name'
        )
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('organizations as sp_org', 'sp_org.id', '=', 'form_access_controls.organization_id')
            ->leftJoin('organizations as fac_org', 'fac_org.id', '=', 'sp_org.parent_id')
            ->leftJoin('form_submissions', function ($join) {
                $join->on('form_submissions.form_id', '=', 'forms.id')
                    ->where('form_submissions.is_submitted', true);
            })
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

        // === 3. Status (dengan subquery DISTINCT agar aman dari duplikasi) ===
        $formPhaseStatus = DB::table(DB::raw('(
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
    ) as distinct_subs'))
            ->select(
                'phase_id as id',
                'phase_title as title',
                DB::raw($this->bucketCount('total_submissions', (new SubmissionStatusBuckets)->countedStates())),
                DB::raw($this->bucketCount('pending', SubmissionStatusBuckets::run()['pending'])),
                DB::raw($this->bucketCount('under_review', SubmissionStatusBuckets::run()['under_review'])),
                DB::raw($this->bucketCount('approved', SubmissionStatusBuckets::run()['approved'])),
                DB::raw($this->bucketCount('rejected', SubmissionStatusBuckets::run()['rejected'])),
                DB::raw($this->bucketCount('revision', SubmissionStatusBuckets::run()['revision'])),
                DB::raw($this->bucketCount('withdrawn', SubmissionStatusBuckets::run()['withdrawn']))
            )
            ->groupBy('phase_id', 'phase_title')
            ->get();

        // === 4. Total (tanpa duplikasi juga, tapi tanpa pecah status) ===
        $formPhaseTotal = FormPhase::select(
            'form_phases.id',
            'form_phases.title'
        )
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('form_submissions', 'form_submissions.form_id', '=', 'forms.id')
            ->groupBy('form_phases.id', 'form_phases.title')
            ->get();

        // === 5. Berdasarkan Periode ===
        $formPhaseByPeriod = FormPhase::select(
            'submission_periods.id as submission_period_id',
            'submission_periods.name as submission_period_name'
        )
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions')
            ->leftJoin('submission_period_phases', 'submission_period_phases.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('submission_periods', 'submission_periods.id', '=', 'submission_period_phases.submission_period_id')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('form_submissions', 'form_submissions.form_id', '=', 'forms.id')
            ->groupBy('submission_periods.id', 'submission_periods.name')
            ->get();

        // === 6. Referensi fakultas & prodi ===
        $faculties = Organization::where('type', 'faculty')
            ->select('id', 'name')->orderBy('name')->get();
        $studyPrograms = Organization::where('type', 'study_program')
            ->select('id', 'name', 'parent_id as faculty_id')->orderBy('name')->get();

        // === Return ===
        return [
            'formPhaseFaculty' => $formPhaseFaculty,
            'formPhaseProdi' => $formPhaseProdi,
            'formPhaseStatus' => $formPhaseStatus,
            'formPhaseTotal' => $formPhaseTotal,
            'formPhaseByPeriod' => $formPhaseByPeriod,
            'faculties' => $faculties,
            'studyPrograms' => $studyPrograms,
        ];
    }

    // get form submission Data
    public function getFormSubmissionStats()
    {
        $recentSubmissions = SubmissionPeriod::select('submission_periods.id', 'submission_periods.name')
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions') // ← perbaikan di sini
            ->leftJoin('submission_period_phases', 'submission_period_phases.submission_period_id', '=', 'submission_periods.id')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'submission_period_phases.form_phase_id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('form_submissions', function ($join) {
                $join->on('form_submissions.form_id', '=', 'forms.id')
                    ->where('form_submissions.is_submitted', '=', true);
            })
            ->where('submission_periods.created_at', '>=', Carbon::now()->subHours(24))
            ->groupBy('submission_periods.id', 'submission_periods.name')
            ->get();

        $totalSubmissions = SubmissionPeriod::select('submission_periods.id', 'submission_periods.name')
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(DISTINCT form_submissions.id) as total_submissions') // ✅ tambahkan DISTINCT
            ->leftJoin('submission_period_phases', 'submission_period_phases.submission_period_id', '=', 'submission_periods.id')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'submission_period_phases.form_phase_id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('form_submissions', function ($join) {
                $join->on('form_submissions.form_id', '=', 'forms.id')
                    ->where('form_submissions.is_submitted', '=', true);
            })
            ->where('submission_periods.created_at', '>=', Carbon::now()->subYears(1))
            ->groupBy('submission_periods.id', 'submission_periods.name')
            ->get();

        $totalByStatus = collect(SubmissionStatusBuckets::run())
            ->map(fn (array $states, string $bucket) => [
                'status' => $bucket,
                'total' => FormSubmission::whereIn('status', $states)->count(),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();

        $totalByFaculty = FormSubmission::select('fac_org.id', 'fac_org.name', DB::raw('COUNT(DISTINCT form_submissions.id) as total'))
            ->join('forms', 'forms.id', '=', 'form_submissions.form_id')
            ->join('form_access_controls', 'form_access_controls.form_id', '=', 'forms.id')
            ->join('organizations as sp_org', function ($join) {
                $join->on('sp_org.id', '=', 'form_access_controls.organization_id')
                    ->where('sp_org.type', 'study_program');
            })
            ->join('organizations as fac_org', 'fac_org.id', '=', 'sp_org.parent_id')
            ->groupBy('fac_org.id', 'fac_org.name')
            ->get();

        $totalByProdi = FormSubmission::select('sp_org.id', 'sp_org.name', DB::raw('COUNT(DISTINCT form_submissions.id) as total'))
            ->join('forms', 'forms.id', '=', 'form_submissions.form_id')
            ->join('form_access_controls', 'form_access_controls.form_id', '=', 'forms.id')
            ->join('organizations as sp_org', function ($join) {
                $join->on('sp_org.id', '=', 'form_access_controls.organization_id')
                    ->where('sp_org.type', 'study_program');
            })
            ->groupBy('sp_org.id', 'sp_org.name')
            ->get();

        $faculties = Organization::where('type', 'faculty')
            ->select('id', 'name')->orderBy('name')->get();
        $studyPrograms = Organization::where('type', 'study_program')
            ->select('id', 'name', 'parent_id as faculty_id')->orderBy('name')->get();

        return [
            'recentSubmissions' => $recentSubmissions,
            'totalSubmissions' => $totalSubmissions,
            'totalByStatus' => $totalByStatus,
            'totalByFaculty' => $totalByFaculty,
            'totalByProdi' => $totalByProdi,
            'faculties' => $faculties,
            'studyPrograms' => $studyPrograms,
        ];
    }

    // get submission reviewer Data
    public function getSubmissionReviewerStats()
    {
        $reviewerRecent = Reviewer::where('reviewers.created_at', '>=', Carbon::now()->subHours(24))
            ->leftJoin('users', 'users.id', '=', 'reviewers.user_id')
            ->select(
                'reviewers.id as id',
                'reviewers.user_id as user_id',
                'users.name as users_name',
                'reviewers.reviewer_type'
            )
            ->orderBy('reviewers.id')
            ->get();

        $totalReviewers = Reviewer::count();

        $totalByRole = Reviewer::select('reviewer_type')
            ->selectRaw('COUNT(id) as total_reviewers')
            ->groupBy('reviewer_type')
            ->get();

        $evaluationStatus = SubmissionReviewer::select('evaluation_status', DB::raw('count(*) as total'))
            ->groupBy('evaluation_status')
            ->get();

        $reviewerByYear = Reviewer::select(DB::raw('EXTRACT(YEAR FROM created_at) as year'), DB::raw('count(*) as total'))
            ->groupByRaw('EXTRACT(YEAR FROM created_at)')
            ->get();

        $reviewerByFaculty = Reviewer::select('parent_org.id', 'parent_org.name', DB::raw('count(*) as total'))
            ->join('user_profiles', 'user_profiles.user_id', '=', 'reviewers.user_id')
            ->join('organizations', 'organizations.id', '=', 'user_profiles.organization_id')
            ->join('organizations as parent_org', 'parent_org.id', '=', 'organizations.parent_id')
            ->where('parent_org.type', 'faculty')
            ->groupBy('parent_org.id', 'parent_org.name')
            ->get();

        $reviewerByProdi = Reviewer::select('organizations.id', 'organizations.name', DB::raw('count(*) as total'))
            ->join('user_profiles', 'user_profiles.user_id', '=', 'reviewers.user_id')
            ->join('organizations', 'organizations.id', '=', 'user_profiles.organization_id')
            ->where('organizations.type', 'study_program')
            ->groupBy('organizations.id', 'organizations.name')
            ->get();

        $reviewerActiveStatus = Reviewer::select('user_id', 'reviewer_type')
            ->groupBy('user_id', 'reviewer_type')
            ->get();

        $faculties = Organization::where('type', 'faculty')
            ->select('id', 'name')->orderBy('name')->get();
        $studyPrograms = Organization::where('type', 'study_program')
            ->select('id', 'name', 'parent_id as faculty_id')->orderBy('name')->get();

        return [
            'reviewerRecent' => $reviewerRecent,
            'totalReviewers' => $totalReviewers,
            'totalByRole' => $totalByRole,
            'evaluationStatus' => $evaluationStatus,
            'reviewerByYear' => $reviewerByYear,
            'reviewerByFaculty' => $reviewerByFaculty,
            'reviewerByProdi' => $reviewerByProdi,
            'reviewerActiveStatus' => $reviewerActiveStatus,
            'faculties' => $faculties,
            'studyPrograms' => $studyPrograms,
        ];
    }

    // get user Data
    public function getUserStats()
    {
        $userRecent = User::where('created_at', '>=', Carbon::now()->subHours(24))
            ->select('id', 'name', 'email', 'created_at')
            ->get();

        $totalUsers = User::count();

        $totalAdmin = User::role('Admin')->count();

        $totalNonAdmin = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'Admin');
        })->count();

        $totalProdi = UserProfile::join('organizations', 'organizations.id', '=', 'user_profiles.organization_id')
            ->where('organizations.type', 'study_program')
            ->distinct()
            ->count('user_profiles.user_id');

        $facultyUnitIds = [];
        foreach (Organization::where('type', 'faculty')->get() as $faculty) {
            $facultyUnitIds = array_merge($facultyUnitIds, Organization::subtreeIds($faculty->id));
        }
        $totalFaculty = UserProfile::whereIn('organization_id', array_unique($facultyUnitIds))
            ->distinct()
            ->count('user_id');

        return [
            'userRecent' => $userRecent,
            'totalUsers' => $totalUsers,
            'totalAdmin' => $totalAdmin,
            'totalNonAdmin' => $totalNonAdmin,
            'totalProdi' => $totalProdi,
            'totalFaculty' => $totalFaculty,
        ];
    }
}
