<?php

namespace App\Http\Controllers;

use App\Actions\Review\SaveEvaluationDraft;
use App\Actions\Review\StartEvaluationForm;
use App\Actions\Review\SubmitEvaluationAnswers;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Requests\Review\EvaluationAnswersRequest;
use App\Http\Requests\Review\StartEvaluationFormRequest;
use App\Models\ReviewerFormAssignment;
use App\Models\User;
use App\Queries\Review\EvaluationAssignmentProgress;
use App\Queries\Review\EvaluationSummaryText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewFormResponseController extends Controller
{
    use ResolvesActor;

    public function showOrCreate(StartEvaluationFormRequest $request)
    {
        $assignment = StartEvaluationForm::run($request->submission(), $request->evaluationForm(), $this->actor($request));

        return $this->toEvaluationPage($assignment);
    }

    /** Nama route lama dipertahankan: `ReviewSystem.vue` operator masih menautnya sampai S4. */
    public function show(Request $request, ReviewerFormAssignment $assignment): RedirectResponse
    {
        $this->viewer($request, $assignment);

        return $this->toEvaluationPage($assignment);
    }

    public function saveDraft(EvaluationAnswersRequest $request, ReviewerFormAssignment $assignment)
    {
        SaveEvaluationDraft::run($assignment, $request->toInput(), $this->actor($request));

        return back()->with('success', 'Draft berhasil disimpan.');
    }

    public function submit(EvaluationAnswersRequest $request, ReviewerFormAssignment $assignment)
    {
        SubmitEvaluationAnswers::run($assignment, $request->toInput(), $this->actor($request));

        // REVIEWER-03-03 menampilkan form yang baru terkunci di tempat yang sama.
        return back()->with('success', 'Formulir evaluasi berhasil dikirim.');
    }

    /** Lembar terkirim kini dibaca di tab formnya sendiri; lihat `show`. */
    public function showSubmitted(Request $request, ReviewerFormAssignment $assignment): RedirectResponse
    {
        return $this->show($request, $assignment);
    }

    public function downloadSummary(Request $request, ReviewerFormAssignment $assignment)
    {
        $this->viewer($request, $assignment);
        $response = $assignment->reviewFormResponse;

        if ($response === null || !$response->isSubmitted()) {
            return back()->withErrors(['error' => 'Tidak ditemukan respons yang telah dikirim.']);
        }

        $summary = EvaluationSummaryText::run($assignment, $response);

        return response()->streamDownload(function () use ($summary) {
            echo $summary;
        }, "evaluation-summary-{$assignment->id}.txt", [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function getProgress(Request $request, ReviewerFormAssignment $assignment)
    {
        $this->viewer($request, $assignment);

        return response()->json(EvaluationAssignmentProgress::run($assignment));
    }

    private function viewer(Request $request, ReviewerFormAssignment $assignment): User
    {
        $actor = $this->actor($request);

        abort_unless($actor->can('view', $assignment), 403, 'Akses tidak sah ke formulir evaluasi ini.');

        return $actor;
    }

    private function toEvaluationPage(ReviewerFormAssignment $assignment): RedirectResponse
    {
        return redirect()->route('reviewer.assignments.evaluation', [
            'submission' => $assignment->submissionReviewer->form_submission_id,
            'form' => $assignment->review_evaluation_form_id,
        ]);
    }
}
