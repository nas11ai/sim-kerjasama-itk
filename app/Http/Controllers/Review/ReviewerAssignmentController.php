<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Queries\Review\ReviewerAssignmentCards;
use App\Queries\Review\ReviewerEvaluation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Layar kerja reviewer: REVIEWER-03-01 sampai 03-04. */
class ReviewerAssignmentController extends Controller
{
    use ResolvesActor;

    public function index(Request $request): Response
    {
        $reviewer = Reviewer::where('user_id', $this->actor($request)->id)->first();
        $cards = $reviewer === null ? ['needsAction' => [], 'done' => []] : ReviewerAssignmentCards::run($reviewer);
        $filter = $request->query('filter') === 'all' ? 'all' : 'action';

        return Inertia::render('Review/AssignmentIndexPage', [
            'needsAction' => $cards['needsAction'],
            'done' => $filter === 'all' ? $cards['done'] : [],
            'filter' => $filter,
        ]);
    }

    public function evaluation(Request $request, FormSubmission $submission): Response
    {
        $assignment = $this->assignmentOf($request, $submission);
        $form = $request->integer('form');

        return Inertia::render('Review/EvaluationPage', ReviewerEvaluation::run($assignment, $form === 0 ? null : $form));
    }

    private function assignmentOf(Request $request, FormSubmission $submission): SubmissionReviewer
    {
        $reviewer = Reviewer::where('user_id', $this->actor($request)->id)->first();
        $assignment = $reviewer === null ? null : ActiveReviewerAssignment::run($submission, $reviewer->id);

        // BR-SM-07, BR-REV-10 — hanya penugasan yang masih berlaku membuka layar ini.
        if ($assignment === null) {
            abort(403, 'Anda tidak ditugaskan sebagai reviewer pengajuan ini.');
        }

        return $assignment;
    }
}
