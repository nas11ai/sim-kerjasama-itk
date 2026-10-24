<?php

namespace App\Http\Controllers;

use App\Actions\Review\BuildReviewerDashboard;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Queries\Review\ReviewerSubmissionList;
use App\Queries\Review\ReviewerSubmissionView;
use App\Queries\Submission\OperatorPeriodDirectory;
use App\Queries\Submission\OperatorPeriodSubmissions;
use App\Queries\Submission\OperatorSubmissionView;
use App\Queries\Submission\ResearcherPeriodOverview;
use App\Queries\Submission\ResearcherPeriodPhases;
use App\Queries\Submission\ResearcherSubmissionView;
use App\Queries\Submission\SubmissionStatusChoices;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionViewController extends Controller
{
    public function userIndex(Request $request): Response
    {
        return Inertia::render('User/Submissions/IndexPage', ResearcherPeriodOverview::run($this->viewer($request)));
    }

    public function adminIndex(Request $request): Response
    {
        return Inertia::render('Submissions/IndexPage', [
            'submissionPeriods' => OperatorPeriodDirectory::run($request->string('search')->value()),
            'filters' => $request->only(['search']),
        ]);
    }

    public function userShowPeriod(Request $request, SubmissionPeriod $period): Response
    {
        return Inertia::render('User/Submissions/ShowPeriod', [
            'submissionPeriod' => $period->load('submissionDates.submissionDateLabel'),
            'formPhases' => ResearcherPeriodPhases::run($period, $this->viewer($request)),
        ]);
    }

    public function adminShowPeriod(SubmissionPeriod $period, Request $request): Response
    {
        $listing = OperatorPeriodSubmissions::run($period, [
            'form_phase_id' => $request->string('form_phase_id')->value(),
            'status' => $request->string('status')->value(),
            'search' => $request->string('search')->value(),
        ]);

        return Inertia::render('Submissions/ShowPeriod', [
            'submissionPeriod' => $period->load('submissionDates.submissionDateLabel'),
            'formPhases' => $listing['formPhases'],
            'submissions' => $listing['submissions'],
            'submissionStatuses' => SubmissionStatusChoices::run(),
            'filters' => $request->only(['form_phase_id', 'status', 'search']),
        ]);
    }

    public function userShowSubmission(Request $request, FormSubmission $submission): Response
    {
        $user = $this->viewer($request);

        // BR-SM-07 — akses level pengajuan adalah lapis terpisah dari FormAccessControl. Cek
        // buatan sendiri di sini dulu melewatkan anggota penelitian dan pemegang submissions.view-all.
        abort_unless($submission->isAccessibleBy($user), 403, 'Akses tidak sah terhadap pengajuan.');

        return Inertia::render('User/Submissions/ShowSubmission', ResearcherSubmissionView::run($user, $submission));
    }

    public function adminShowSubmission(Request $request, FormSubmission $submission): Response
    {
        // Draft belum diajukan; bagi Operator ia belum ada.
        abort_unless($submission->is_submitted, 404, 'Pengajuan tidak ditemukan.');

        return Inertia::render('Submissions/ShowSubmission', OperatorSubmissionView::run($this->viewer($request), $submission));
    }

    /** Dasbor reviewer: penugasan aktif dan sisa pekerjaannya. */
    public function reviewerDashboard(Request $request): Response
    {
        $reviewer = $this->reviewerOf($request);
        $dashboard = BuildReviewerDashboard::run($reviewer);

        return Inertia::render('Reviewer/DashboardPage', [
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type,
                'user' => ['name' => $reviewer->user?->name, 'email' => $reviewer->user?->email],
            ],
            'stats' => $dashboard['stats'],
            'assignments' => $dashboard['assignments'],
        ]);
    }

    public function reviewerSubmissions(Request $request): Response
    {
        $reviewer = $this->reviewerOf($request);

        return Inertia::render('Reviewer/Submissions/IndexPage', [
            'submissions' => ReviewerSubmissionList::run(
                $reviewer,
                $request->string('status')->value(),
                $request->string('search')->value(),
            ),
            'filters' => $request->only(['status', 'search']),
            'reviewer' => ['id' => $reviewer->id, 'reviewer_type' => $reviewer->reviewer_type],
        ]);
    }

    public function reviewerShowSubmission(Request $request, FormSubmission $submission): Response
    {
        $reviewer = $this->reviewerOf($request);

        // BR-SM-07 — hanya penugasan yang masih berlaku yang memberi akses; record reviewer yang
        // sudah diganti disimpan sebagai sejarah (BR-REV-10).
        abort_if(
            ActiveReviewerAssignment::run($submission, $reviewer->id) === null,
            403,
            'Anda tidak ditugaskan untuk review pengajuan ini.',
        );

        return Inertia::render('Reviewer/Submissions/ShowPage', ReviewerSubmissionView::run($reviewer, $submission));
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function reviewerOf(Request $request): Reviewer
    {
        $reviewer = Reviewer::where('user_id', $this->viewer($request)->id)->first();
        abort_if($reviewer === null, 403, 'Anda tidak terdaftar sebagai reviewer.');

        return $reviewer;
    }
}
