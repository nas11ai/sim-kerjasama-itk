<?php

namespace App\Http\Controllers;

use App\Actions\Form\AssertFormAccessible;
use App\Actions\FormSubmission\SaveSubmissionDraft;
use App\Actions\FormSubmission\StartSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Actions\Review\BuildReviewerDashboard;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Requests\Submission\StartSubmissionRequest;
use App\Http\Requests\Submission\SubmissionAnswersRequest;
use App\Models\FormPhase;
use App\Models\Reviewer;
use App\Models\SubmissionPeriod;
use App\Queries\Submission\AccessibleFormPhaseDetail;
use App\Queries\Submission\ResearcherDashboardPeriods;
use App\Queries\Submission\ResearcherFormPhaseSteps;
use App\Queries\Submission\ResearcherStudyProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserFormController extends Controller
{
    use ResolvesActor;

    public function dashboard(Request $request): Response
    {
        $user = $this->actor($request);
        $reviewer = Reviewer::firstWhere('user_id', $user->id);

        return Inertia::render('User/DashboardPage', [
            'submissionPeriods' => ResearcherDashboardPeriods::run($user),
            'userRole' => $user->getRoleNames()->first() ?? 'user',
            'studyProgram' => ResearcherStudyProgram::run($user),
            'isReviewer' => $reviewer !== null,
            // Dihitung dari penugasan, sama dengan dasbor reviewer. Dulu dari ulir diskusi,
            // termasuk ulir penugasan yang sudah diganti (BR-REV-10).
            'reviewStats' => $reviewer === null ? null : BuildReviewerDashboard::run($reviewer)['stats'],
            'reviewer' => $reviewer === null ? null : ['id' => $reviewer->id, 'reviewer_type' => $reviewer->reviewer_type],
        ]);
    }

    public function showFormPhase(Request $request, SubmissionPeriod $period, FormPhase $phase): Response|RedirectResponse
    {
        $steps = ResearcherFormPhaseSteps::run($period, $phase, $this->actor($request));

        if ($steps === []) {
            return redirect()->route('user.dashboard')
                ->with('error', 'Anda tidak memiliki akses ke tahap formulir ini.');
        }

        return Inertia::render('User/FormPhase', [
            'submissionPeriod' => ['id' => $period->id, 'name' => $period->name],
            'formPhase' => [
                'id' => $phase->id,
                'title' => $phase->title,
                'description' => $phase->description,
                'form_access_controls' => $steps,
            ],
            'currentStep' => $request->integer('step', 1),
        ]);
    }

    public function startSubmission(StartSubmissionRequest $request): RedirectResponse
    {
        $user = $this->actor($request);

        AssertFormAccessible::run($user, $request->form(), 'Anda tidak memiliki akses ke formulir ini.');

        // Gagal keras kalau tidak ketemu, bukan menulis NULL ke form_phase_detail_id.
        $detail = AccessibleFormPhaseDetail::run($request->integer('form_phase_id'), $request->integer('form_id'), $user)
            ?? abort(422, 'Kombinasi form dan fase formulir tidak ditemukan.');

        StartSubmission::run($request->integer('form_id'), $request->integer('submission_period_id'), $detail, $user);

        return redirect()->back();
    }

    public function saveDraft(SubmissionAnswersRequest $request): RedirectResponse
    {
        SaveSubmissionDraft::run($request->toInput(), $this->actor($request));

        return redirect()->back()->with('success', 'Draft berhasil disimpan.');
    }

    public function submitForm(SubmissionAnswersRequest $request): RedirectResponse
    {
        $user = $this->actor($request);
        $submission = SaveSubmissionDraft::run($request->toInput(), $user);

        // BR-SM-01, BR-SM-02, BR-SM-04, BR-SM-16 — seluruh keputusan submit ada di Action. Cabang
        // needs_review yang dulu di sini dicabut: ia memanggil transitionTo(Approved) dari DRAFT, transisi
        // yang tidak diizinkan state machine, sehingga selalu melempar TransitionNotFound (ADR 0016).
        SubmitFormSubmission::run($submission, $user);

        return redirect()->back()->with('success', 'Formulir berhasil diserahkan.');
    }
}
