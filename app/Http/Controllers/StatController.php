<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\FormSubmission;
use App\Models\FormPhase;
use App\Models\Reviewer;
use App\Models\ReviewerRole;
use App\Models\StudyProgram;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class StatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formPhase = $this->getFormPhaseStats();
        $formSubmission = $this->getFormSubmissionStats();
        $user = $this->getUserStats();
        $submissionReviewer = $this->getSubmissionReviewerStats();

        // dd($formPhase, $formSubmission, $user, $submissionReviewer);
        return Inertia::render('Statistics/Index', [
            'formPhase' => $formPhase,
            'formSubmission' => $formSubmission,
            'user' => $user,
            'submissionReviewer' => $submissionReviewer,
        ]);
    }

    // form phase Index
    public function formPhaseStatIndex()
    {
        return Inertia::render('Statistics/FormPhaseStats',
            $this->getFormPhaseStats(),
        );
    }

    // form submission Index
    public function formSubmissionStatIndex()
    {
        return Inertia::render('Statistics/FormSubmissionStats',
            $this->getFormSubmissionStats(),
        );
    }

    // submission reviewer Index
    public function submissionReviewerStatIndex()
    {
        return Inertia::render('Statistics/SubmissionReviewerStats',
            $this->getSubmissionReviewerStats()
        );
    }

    // user Index
    public function userStatIndex()
    {
        return Inertia::render('Statistics/UserStats',
            $this->getUserStats(),
        );
    }

    //tes data
    public function data()
    {
        return response()->json([
            'formPhase' => $this->getFormPhaseStats(),
            'formSubmission' => $this->getFormSubmissionStats(),
            'user' => $this->getUserStats(),
            'submissionReviewer' => $this->getSubmissionReviewerStats(),
        ]);
    }

    // get form phase Data
    public function getFormPhaseStats()
    {
        $formPhaseFaculty = FormPhase::select(
            'form_phases.id',
            'form_phases.title',
            'faculties.id as faculty_id',
            'faculties.name as faculty_name'
        )
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(form_submissions.id) as total_submissions')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('study_programs', 'study_programs.id', '=', 'form_access_controls.study_program_id')
            ->leftJoin('faculties', 'faculties.id', '=', 'study_programs.faculty_id')
            ->leftJoin('form_submissions', function ($join) {
                $join->on('form_submissions.form_id', '=', 'forms.id')
                    ->where('form_submissions.is_submitted', '=', true);
            })
            ->groupBy('form_phases.id', 'form_phases.title', 'faculties.id', 'faculties.name')
            ->get();

        $formPhaseProdi = FormPhase::select(
            'form_phases.id',
            'form_phases.title',
            'faculties.id as faculty_id',
            'faculties.name as faculty_name',
            'study_programs.id as study_program_id',
            'study_programs.name as study_program_name'
        )
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(form_submissions.id) as total_submissions')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('study_programs', 'study_programs.id', '=', 'form_access_controls.study_program_id')
            ->leftJoin('faculties', 'faculties.id', '=', 'study_programs.faculty_id')
            ->leftJoin('form_submissions', function ($join) {
                $join->on('form_submissions.form_id', '=', 'forms.id')
                    ->where('form_submissions.is_submitted', '=', true);
            })
            ->groupBy(
                'form_phases.id',
                'form_phases.title',
                'faculties.id',
                'faculties.name',
                'study_programs.id',
                'study_programs.name'
            )
            ->orderBy('faculties.name', 'asc')
            ->orderBy('study_programs.name', 'asc')
            ->orderBy('form_phases.id', 'asc')
            ->get();

        $formPhaseStatus = FormPhase::select('form_phases.id', 'form_phases.title')
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(form_submissions.id) as total_submissions')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('form_submissions', 'form_submissions.form_id', '=', 'forms.id')
            ->select(
                'form_phases.id',
                'form_phases.title',
                DB::raw('COUNT(DISTINCT forms.id) as total_forms'),
                DB::raw('COUNT(form_submissions.id) as total_submissions'),
                DB::raw('SUM(form_submissions.status = "pending") as pending'),
                DB::raw('SUM(form_submissions.status = "under_review") as under_review'),
                DB::raw('SUM(form_submissions.status = "approved") as approved'),
                DB::raw('SUM(form_submissions.status = "rejected") as rejected'),
                DB::raw('SUM(form_submissions.status = "revision") as revision')
            )
            ->groupBy('form_phases.id', 'form_phases.title')
            ->get();

        $formPhaseTotal = FormPhase::select('form_phases.id', 'form_phases.title')
            ->selectRaw('COUNT(DISTINCT forms.id) as total_forms')
            ->selectRaw('COUNT(form_submissions.id) as total_submissions')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('form_submissions', 'form_submissions.form_id', '=', 'forms.id')
            ->select(
                'form_phases.id',
                'form_phases.title',
                DB::raw('COUNT(DISTINCT forms.id) as total_forms'),
                DB::raw('COUNT(form_submissions.id) as total_submissions'),
                DB::raw('SUM(form_submissions.status = "pending") as pending'),
                DB::raw('SUM(form_submissions.status = "under_review") as under_review'),
                DB::raw('SUM(form_submissions.status = "approved") as approved'),
                DB::raw('SUM(form_submissions.status = "rejected") as rejected'),
                DB::raw('SUM(form_submissions.status = "revision") as revision')
            )
            ->groupBy('form_phases.id', 'form_phases.title')
            ->get();

        $formPhaseByPeriod = FormPhase::select(
            'submission_periods.id as submission_period_id',
            'submission_periods.name as submission_period_name',
            DB::raw('COUNT(DISTINCT forms.id) as total_forms'),
            DB::raw('COUNT(form_submissions.id) as total_submissions')
        )
            ->leftJoin('submission_period_phases', 'submission_period_phases.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('submission_periods', 'submission_periods.id', '=', 'submission_period_phases.submission_period_id')
            ->leftJoin('form_phase_details', 'form_phase_details.form_phase_id', '=', 'form_phases.id')
            ->leftJoin('form_access_controls', 'form_access_controls.id', '=', 'form_phase_details.form_access_control_id')
            ->leftJoin('forms', 'forms.id', '=', 'form_access_controls.form_id')
            ->leftJoin('form_submissions', 'form_submissions.form_id', '=', 'forms.id')
            ->groupBy('submission_periods.id', 'submission_periods.name')
            ->get();

        $faculties = Faculty::select('id', 'name')->orderBy('name')->get();
        $studyPrograms = StudyProgram::select('id', 'name', 'faculty_id')->orderBy('name')->get();

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

        $totalByStatus = FormSubmission::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $totalByFaculty = FormSubmission::select('faculties.name', DB::raw('COUNT(DISTINCT form_submissions.id) as total'))
            ->join('forms', 'forms.id', '=', 'form_submissions.form_id')
            ->join('form_access_controls', 'form_access_controls.form_id', '=', 'forms.id')
            ->join('study_programs', 'study_programs.id', '=', 'form_access_controls.study_program_id')
            ->join('faculties', 'faculties.id', '=', 'study_programs.faculty_id')
            ->groupBy('faculties.name')
            ->get();

        $totalByProdi = FormSubmission::select('study_programs.name', DB::raw('COUNT(DISTINCT form_submissions.id) as total'))
            ->join('forms', 'forms.id', '=', 'form_submissions.form_id')
            ->join('form_access_controls', 'form_access_controls.form_id', '=', 'forms.id')
            ->join('study_programs', 'study_programs.id', '=', 'form_access_controls.study_program_id')
            ->groupBy('study_programs.name')
            ->get();

        $faculties = Faculty::select('id', 'name')->orderBy('name')->get();
        $studyPrograms = StudyProgram::select('id', 'name', 'faculty_id')->orderBy('name')->get();

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
            ->leftJoin('reviewer_roles', 'reviewer_roles.id', '=', 'reviewers.reviewer_role_id')
            ->select(
                'reviewers.id as id',
                'reviewers.user_id as user_id',
                'users.name as users_name',
                'reviewers.reviewer_role_id as reviewer_role_id'
            )
            ->orderBy('reviewers.id')
            ->get();

        $totalReviewers = Reviewer::count();

        $totalByRole = ReviewerRole::select('reviewer_roles.id', 'reviewer_roles.name as reviewer_role_name')
            ->selectRaw('COUNT(reviewers.id) as total_reviewers')
            ->leftJoin('reviewers', 'reviewers.reviewer_role_id', '=', 'reviewer_roles.id')
            ->groupBy('reviewer_roles.id', 'reviewer_roles.name')
            ->get();

        $evaluationStatus = SubmissionReviewer::select('evaluation_status', DB::raw('count(*) as total'))
            ->groupBy('evaluation_status')
            ->get();

        $reviewerByYear = Reviewer::select(DB::raw('YEAR(created_at) as year'), DB::raw('count(*) as total'))
            ->groupBy('year')
            ->get();

        $reviewerByFaculty = Reviewer::select('faculties.name', DB::raw('count(*) as total'))
            ->join('user_profiles', 'user_profiles.user_id', '=', 'reviewers.user_id')
            ->join('study_programs', 'study_programs.id', '=', 'user_profiles.study_program_id')
            ->join('faculties', 'faculties.id', '=', 'study_programs.faculty_id')
            ->groupBy('faculties.name')
            ->get();

        $reviewerByProdi = Reviewer::select('study_programs.name', DB::raw('count(*) as total'))
            ->join('user_profiles', 'user_profiles.user_id', '=', 'reviewers.user_id')
            ->join('study_programs', 'study_programs.id', '=', 'user_profiles.study_program_id')
            ->groupBy('study_programs.name')
            ->get();

        $reviewerActiveStatus = Reviewer::select('user_id', 'reviewer_role_id')
            ->leftJoin('reviewer_roles', 'reviewer_roles.id', '=', 'reviewers.reviewer_role_id')
            ->where('reviewer_roles.is_active', '=', 1)
            ->groupBy('user_id', 'reviewer_role_id', 'is_active')
            ->get();

        $faculties = Faculty::select('id', 'name')->orderBy('name')->get();
        $studyPrograms = StudyProgram::select('id', 'name', 'faculty_id')->orderBy('name')->get();

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

        $totalProdi = UserProfile::where('study_program_id', '!=', null)->count();

        $totalFaculty = UserProfile::select('users.id')
            ->join('study_programs', 'user_profiles.study_program_id', '=', 'study_programs.id')
            ->join('faculties', 'study_programs.faculty_id', '=', 'faculties.id')
            ->where('faculties.id', '=', 1)
            ->count();

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
