<?php

namespace App\Http\Controllers;

use App\Actions\Review\AssignEvaluationFormsInBulk;
use App\Actions\Review\RemoveEvaluationFormAssignment;
use App\Data\Request\FormAssignmentInput;
use App\Data\Review\BulkFormAssignmentResult;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Queries\Review\ReviewerAssignmentList;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ReviewerFormAssignmentController extends Controller
{
    public function removeFormAssignment(ReviewerFormAssignment $assignment)
    {
        RemoveEvaluationFormAssignment::run($assignment);

        return back()->with('success', 'Penugasan formulir berhasil dihapus.');
    }

    public function bulkAssignForms(Request $request, FormSubmission $submission)
    {
        $validated = $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'integer|exists:reviewers,id',
            'form_assignments' => 'required|array',
            'form_assignments.*.form_id' => 'required|exists:review_evaluation_forms,id',
            'form_assignments.*.is_required' => 'boolean',
            'form_assignments.*.due_date' => 'nullable|date|after:now',
        ]);

        $forms = array_map(
            fn (array $row) => new FormAssignmentInput(
                (int) $row['form_id'],
                $row['is_required'] ?? true,
                $row['due_date'] ? Carbon::parse($row['due_date']) : null,
            ),
            array_values($validated['form_assignments']),
        );

        $result = AssignEvaluationFormsInBulk::run($submission, array_map('intval', $validated['reviewer_ids']), $forms);

        return back()->with('success', $this->bulkMessage($result));
    }

    public function getMyAssignments(Request $request)
    {
        $reviewer = $request->user()->reviewers()->first();

        if (!$reviewer) {
            return Inertia::render('Reviewer/Assignments/IndexPage', [
                'assignments' => [],
                'stats' => ['total' => 0, 'pending' => 0, 'completed' => 0, 'overdue' => 0],
            ]);
        }

        $listing = ReviewerAssignmentList::run(
            $reviewer,
            $request->query('status'),
            $request->query('search'),
        );

        return Inertia::render('Reviewer/Assignments/IndexPage', [
            'assignments' => $listing->assignments,
            'stats' => $listing->stats,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    private function bulkMessage(BulkFormAssignmentResult $result): string
    {
        $message = "Berhasil menugaskan form kepada {$result->assignedCount} reviewer.";

        if ($result->skippedReviewerIds === []) {
            return $message;
        }

        $ids = implode(', ', $result->skippedReviewerIds);

        return "{$message} Dilewati karena tidak ditugaskan pada pengajuan ini: reviewer ID {$ids}.";
    }
}
