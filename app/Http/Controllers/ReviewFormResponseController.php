<?php

namespace App\Http\Controllers;

use App\Actions\Review\SaveEvaluationDraft;
use App\Actions\Review\StartEvaluationForm;
use App\Actions\Review\SubmitEvaluationAnswers;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Requests\Review\EvaluationAnswersRequest;
use App\Http\Requests\Review\StartEvaluationFormRequest;
use App\Models\ReviewerFormAssignment;
use Illuminate\Http\RedirectResponse;

class ReviewFormResponseController extends Controller
{
    use ResolvesActor;

    public function showOrCreate(StartEvaluationFormRequest $request)
    {
        $assignment = StartEvaluationForm::run($request->submission(), $request->evaluationForm(), $this->actor($request));

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

    private function toEvaluationPage(ReviewerFormAssignment $assignment): RedirectResponse
    {
        return redirect()->route('reviewer.assignments.evaluation', [
            'submission' => $assignment->submissionReviewer->form_submission_id,
            'form' => $assignment->review_evaluation_form_id,
        ]);
    }
}
