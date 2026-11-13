<?php

namespace App\Http\Controllers\Monev;

use App\Actions\Monev\AssignCycleReviewer;
use App\Actions\Monev\OpenMonevCycle;
use App\Data\View\Submission\Manage\ReviewerCandidateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Monev\AssignCycleReviewerRequest;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Monev\MonevCycleBoard;
use App\Queries\Monev\MonevCycleManagement;
use App\Queries\Monev\MonevCycles;
use App\Queries\Review\ReviewerCandidates;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layar monev sisi Operator (OPERATOR-05-01, 05-02). Dijaga `can:submissions.view-all`
 * di route (W129, BR-SM-07); penugasan reviewer juga oleh Policy `assignMonevReviewer`.
 */
class MonevManageController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'period' => $request->string('period')->value(),
            'scheme' => $request->string('scheme')->value(),
        ];

        return Inertia::render('Monev/ManageIndexPage', [
            'filters' => $filters,
            ...MonevCycleBoard::run($filters),
        ]);
    }

    public function show(Request $request, FormSubmission $submission): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);
        // Hanya induk yang sudah disetujui (atau ditarik sesudahnya) punya siklus untuk dikelola.
        abort_unless($submission->parent_submission_id === null
            && ($submission->status instanceof Approved || $submission->status instanceof Withdrawn), 404);

        return Inertia::render('Monev/ManageShowPage', [
            ...MonevCycleManagement::run($viewer, $submission),
            // Hanya lewat partial reload dialog penugasan, jadi pencarian tetap di server.
            'reviewerCandidates' => Inertia::optional(fn () => $this->candidates($request, $submission)),
        ]);
    }

    public function open(Request $request, FormSubmission $submission, FormPhaseDetail $detail): RedirectResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof User, 403);

        $notified = OpenMonevCycle::run($submission, $detail, $operator);

        return back()->with('success', "Siklus dibuka. {$notified} orang diberi tahu.");
    }

    public function assignReviewer(AssignCycleReviewerRequest $request, FormSubmission $submission, FormPhaseDetail $detail): RedirectResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof User, 403);

        AssignCycleReviewer::run($submission, $detail, $request->reviewer(), $operator);

        return back()->with('success', 'Penilai ditugaskan.');
    }

    /** @return list<ReviewerCandidateData> */
    private function candidates(Request $request, FormSubmission $parent): array
    {
        $detail = MonevCycles::details($parent)->firstWhere('id', $request->integer('reviewer_cycle'));

        if ($detail === null) {
            return [];
        }

        return ReviewerCandidates::run(
            MonevCycleManagement::candidateSubject($parent, $detail),
            $request->string('reviewer_search')->trim()->toString(),
        );
    }
}
