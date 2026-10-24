<?php

namespace App\Http\Controllers;

use App\Actions\Review\OpenEvaluationResponse;
use App\Actions\Review\SaveEvaluationDraft;
use App\Actions\Review\StartEvaluationForm;
use App\Actions\Review\SubmitEvaluationAnswers;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Requests\Review\EvaluationAnswersRequest;
use App\Http\Requests\Review\StartEvaluationFormRequest;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormResponse;
use App\Models\User;
use App\Queries\Review\EvaluationAssignmentProgress;
use App\Queries\Review\EvaluationFormCompletion;
use App\Queries\Review\EvaluationSummaryText;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewFormResponseController extends Controller
{
    use ResolvesActor;

    public function showOrCreate(StartEvaluationFormRequest $request)
    {
        $assignment = StartEvaluationForm::run($request->submission(), $request->evaluationForm(), $this->actor($request));

        return redirect()->route('reviewer.evaluation-form.show', $assignment->id);
    }

    public function show(Request $request, ReviewerFormAssignment $assignment)
    {
        $actor = $this->viewer($request, $assignment);
        $canEvaluate = $actor->can('evaluate', $assignment);
        $this->loadSheet($assignment);

        // Draft dibuat hanya bagi reviewernya; admin yang sekadar membaca tidak meninggalkan apa pun.
        $response = $canEvaluate
            ? OpenEvaluationResponse::run($assignment, $actor)
            : $assignment->reviewFormResponse ?? new ReviewFormResponse(['reviewer_form_assignment_id' => $assignment->id, 'status' => 'draft']);

        return Inertia::render('Reviewer/EvaluationForm/ShowPage', [
            'assignment' => $assignment,
            'response' => $response,
            'existingResponses' => $response->reviewFormFieldResponses->pluck('value', 'review_form_field_id'),
            'canEdit' => $canEvaluate && $response->canBeEdited(),
            'canSubmit' => $canEvaluate && $response->canBeSubmitted(),
            'completionPercentage' => EvaluationFormCompletion::run($response),
        ]);
    }

    public function saveDraft(EvaluationAnswersRequest $request, ReviewerFormAssignment $assignment)
    {
        SaveEvaluationDraft::run($assignment, $request->toInput(), $this->actor($request));

        return back()->with('success', 'Draft berhasil disimpan.');
    }

    public function submit(EvaluationAnswersRequest $request, ReviewerFormAssignment $assignment)
    {
        SubmitEvaluationAnswers::run($assignment, $request->toInput(), $this->actor($request));

        return redirect()->route('reviewer.assignments.index')
            ->with('success', 'Formulir evaluasi berhasil dikirim.');
    }

    public function showSubmitted(Request $request, ReviewerFormAssignment $assignment)
    {
        $this->viewer($request, $assignment);
        $response = $assignment->reviewFormResponse;

        if ($response === null || !$response->isSubmitted()) {
            return redirect()->route('reviewer.evaluation-form.show', $assignment);
        }

        $this->loadSheet($assignment);

        return Inertia::render('Reviewer/EvaluationForm/SubmittedPage', [
            'assignment' => $assignment,
            'response' => $response,
            'formattedResponses' => $response->reviewFormFieldResponses->mapWithKeys(fn ($answer) => [
                $answer->review_form_field_id => ['value' => $answer->value, 'formatted_value' => $answer->formatted_value],
            ]),
        ]);
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

    private function loadSheet(ReviewerFormAssignment $assignment): void
    {
        $assignment->load([
            'reviewEvaluationForm.reviewFormFields' => fn (Relation $fields) => $fields->orderBy('order')->with([
                'fieldType:id,name',
                'reviewFormFieldOptions' => fn (Relation $options) => $options->orderBy('order'),
            ]),
            'submissionReviewer.formSubmission.form:id,title',
            'submissionReviewer.formSubmission.submittedBy:id,name',
            'reviewFormResponse.reviewFormFieldResponses',
        ]);
    }
}
