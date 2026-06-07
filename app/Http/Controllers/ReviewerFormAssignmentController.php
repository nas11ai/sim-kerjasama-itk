<?php

namespace App\Http\Controllers;

use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReviewerFormAssignmentController extends Controller
{
    public function assignFormsToReviewer(Request $request, FormSubmission $submission, int $reviewerId)
    {
        $validated = $request->validate([
            'evaluation_form_ids' => 'required|array|min:1',
            'evaluation_form_ids.*' => 'exists:review_evaluation_forms,id',
            'assignments' => 'required|array',
            'assignments.*.form_id' => 'required|exists:review_evaluation_forms,id',
            'assignments.*.is_required' => 'boolean',
            'assignments.*.due_date' => 'nullable|date|after:now',
        ]);

        // Find submission reviewer
        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewerId,
        ])->first();

        if (!$submissionReviewer) {
            return back()->withErrors(['error' => 'Reviewer tidak ditugaskan untuk pengajuan ini.']);
        }

        try {
            DB::beginTransaction();

            foreach ($validated['assignments'] as $assignment) {
                // Check if form belongs to the same phase as submission
                /** @var ReviewEvaluationForm $evaluationForm */
                $evaluationForm = ReviewEvaluationForm::with('formPhaseDetail')
                    ->findOrFail($assignment['form_id']);

                // Validate form phase matches submission form phase
                $formPhase = $this->getSubmissionFormPhase($submission);
                if (!$formPhase || $evaluationForm->formPhaseDetail?->form_phase_id !== $formPhase->id) {
                    throw new \Exception("Formulir evaluasi '{$evaluationForm->title}' tidak termasuk dalam tahap formulir yang benar.");
                }

                // Create or update assignment
                ReviewerFormAssignment::updateOrCreate(
                    [
                        'submission_reviewer_id' => $submissionReviewer->id,
                        'review_evaluation_form_id' => $assignment['form_id'],
                    ],
                    [
                        'is_required' => $assignment['is_required'] ?? true,
                        'due_date' => $assignment['due_date'] ? Carbon::parse($assignment['due_date']) : null,
                        'assigned_at' => now(),
                        'is_active' => true,
                    ]
                );
            }

            // Update submission reviewer evaluation status
            $submissionReviewer->updateEvaluationStatus();

            DB::commit();

            return back()->with('success', 'Formulir evaluasi berhasil ditugaskan.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menugaskan formulir evaluasi: '.$e->getMessage()]);
        }
    }

    public function removeFormAssignment(ReviewerFormAssignment $assignment)
    {
        // Check if there are any submitted responses
        if ($assignment->reviewFormResponse && $assignment->reviewFormResponse->isSubmitted()) {
            return back()->withErrors(['error' => 'Tidak dapat menghapus penugasan dengan respons yang telah dikirim.']);
        }

        try {
            DB::beginTransaction();

            $submissionReviewer = $assignment->submissionReviewer;

            // Delete related draft responses
            if ($assignment->reviewFormResponse && $assignment->reviewFormResponse->isDraft()) {
                $assignment->reviewFormResponse->reviewFormFieldResponses()->delete();
                $assignment->reviewFormResponse->delete();
            }

            $assignment->delete();

            // Update evaluation status
            $submissionReviewer->updateEvaluationStatus();

            DB::commit();

            return back()->with('success', 'Penugasan formulir berhasil dihapus.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menghapus penugasan formulir: '.$e->getMessage()]);
        }
    }

    public function updateAssignment(Request $request, ReviewerFormAssignment $assignment)
    {
        $validated = $request->validate([
            'is_required' => 'boolean',
            'due_date' => 'nullable|date|after:now',
        ]);

        // Check if response is already submitted
        if ($assignment->reviewFormResponse && $assignment->reviewFormResponse->isSubmitted()) {
            return back()->withErrors(['error' => 'Tidak dapat memodifikasi penugasan dengan respons yang telah dikirim.']);
        }

        try {
            $assignment->update([
                'is_required' => $validated['is_required'] ?? $assignment->is_required,
                'due_date' => $validated['due_date'] ? Carbon::parse($validated['due_date']) : null,
            ]);

            // Update evaluation status
            $assignment->submissionReviewer->updateEvaluationStatus();

            return back()->with('success', 'Penugasan berhasil diperbarui.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal memperbarui penugasan: '.$e->getMessage()]);
        }
    }

    public function getAssignmentsForSubmission(FormSubmission $submission)
    {
        $assignments = ReviewerFormAssignment::whereHas('submissionReviewer', function ($query) use ($submission) {
            $query->where('form_submission_id', $submission->id);
        })
            ->with([
                'submissionReviewer.reviewer.user:id,name,email',
                'submissionReviewer.reviewer.reviewerRole:id,name',
                'reviewEvaluationForm:id,title,description,is_required',
                'reviewFormResponse:id,reviewer_form_assignment_id,status,submitted_at',
            ])
            ->get()
            ->groupBy('submissionReviewer.reviewer.id');

        return response()->json($assignments);
    }

    public function getAvailableFormsForSubmission(FormSubmission $submission)
    {
        $formPhase = $this->getSubmissionFormPhase($submission);

        if (!$formPhase) {
            return response()->json([]);
        }

        $availableForms = $formPhase->activeReviewEvaluationForms()
            ->get(['id', 'title', 'description', 'is_required', 'order']);

        return response()->json($availableForms);
    }

    public function bulkAssignForms(Request $request, FormSubmission $submission)
    {
        $validated = $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'exists:reviewers,id',
            'form_assignments' => 'required|array',
            'form_assignments.*.form_id' => 'required|exists:review_evaluation_forms,id',
            'form_assignments.*.is_required' => 'boolean',
            'form_assignments.*.due_date' => 'nullable|date|after:now',
        ]);

        try {
            DB::beginTransaction();

            $successCount = 0;
            $errors = [];

            foreach ($validated['reviewer_ids'] as $reviewerId) {
                $submissionReviewer = SubmissionReviewer::where([
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewerId,
                ])->first();

                if (!$submissionReviewer) {
                    $errors[] = "Reviewer ID {$reviewerId} tidak ditugaskan untuk pengajuan ini";

                    continue;
                }

                foreach ($validated['form_assignments'] as $assignment) {
                    ReviewerFormAssignment::updateOrCreate(
                        [
                            'submission_reviewer_id' => $submissionReviewer->id,
                            'review_evaluation_form_id' => $assignment['form_id'],
                        ],
                        [
                            'is_required' => $assignment['is_required'] ?? true,
                            'due_date' => $assignment['due_date'] ? Carbon::parse($assignment['due_date']) : null,
                            'assigned_at' => now(),
                            'is_active' => true,
                        ]
                    );
                }

                $submissionReviewer->updateEvaluationStatus();
                $successCount++;
            }

            DB::commit();

            $message = "Successfully assigned forms to {$successCount} reviewer(s).";
            if (!empty($errors)) {
                $message .= ' Errors: '.implode(', ', $errors);
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menugaskan formulir secara massal: '.$e->getMessage()]);
        }
    }

    public function getMyAssignments(Request $request)
    {
        $user = Auth::user();
        $reviewer = $user->reviewers()->first();

        if (!$reviewer) {
            return Inertia::render('Reviewer/Assignments/Index', [
                'assignments' => [],
                'stats' => [
                    'total' => 0,
                    'pending' => 0,
                    'completed' => 0,
                    'overdue' => 0,
                ],
            ]);
        }

        $query = ReviewerFormAssignment::where('submission_reviewer_id', function ($subQuery) use ($reviewer) {
            $subQuery->select('id')
                ->from('submission_reviewers')
                ->where('reviewer_id', $reviewer->id);
        })->with([
            'reviewEvaluationForm:id,title,description',
            'submissionReviewer.formSubmission.form:id,title',
            'submissionReviewer.formSubmission.submittedBy:id,name',
            'reviewFormResponse:id,reviewer_form_assignment_id,status,submitted_at',
        ]);

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            switch ($request->status) {
                case 'pending':
                    $query->whereDoesntHave('reviewFormResponse', function ($q) {
                        $q->where('status', 'submitted');
                    });
                    break;
                case 'completed':
                    $query->whereHas('reviewFormResponse', function ($q) {
                        $q->where('status', 'submitted');
                    });
                    break;
                case 'overdue':
                    $query->where('due_date', '<', now())
                        ->whereDoesntHave('reviewFormResponse', function ($q) {
                            $q->where('status', 'submitted');
                        });
                    break;
            }
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('reviewEvaluationForm', function ($subQ) use ($search) {
                    $subQ->where('title', 'ilike', "%{$search}%");
                })
                    ->orWhereHas('submissionReviewer.formSubmission.form', function ($subQ) use ($search) {
                        $subQ->where('title', 'ilike', "%{$search}%");
                    });
            });
        }

        $assignments = $query->orderByRaw('
            CASE
                WHEN due_date IS NOT NULL AND due_date < NOW() THEN 1
                WHEN due_date IS NOT NULL THEN 2
                ELSE 3
            END
        ')
            ->orderBy('due_date', 'asc')
            ->paginate(15);

        // Calculate stats
        $allAssignments = ReviewerFormAssignment::where('submission_reviewer_id', function ($subQuery) use ($reviewer) {
            $subQuery->select('id')
                ->from('submission_reviewers')
                ->where('reviewer_id', $reviewer->id);
        })->get();

        $stats = [
            'total' => $allAssignments->count(),
            'pending' => $allAssignments->filter(fn ($a) => !$a->isCompleted())->count(),
            'completed' => $allAssignments->filter(fn ($a) => $a->isCompleted())->count(),
            'overdue' => $allAssignments->filter(fn ($a) => $a->isOverdue())->count(),
        ];

        return Inertia::render('Reviewer/Assignments/Index', [
            'assignments' => $assignments,
            'stats' => $stats,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    protected function getSubmissionFormPhase(FormSubmission $submission)
    {
        return FormPhase::whereHas('formPhaseDetails.formAccessControl', function ($query) use ($submission) {
            $query->where('form_id', $submission->form_id);
        })->first();
    }

    public function autoLockOverdueAssignments()
    {
        try {
            $overdueAssignments = ReviewerFormAssignment::where('due_date', '<', now())
                ->whereHas('reviewFormResponse', function ($query) {
                    $query->where('status', 'draft');
                })
                ->with('reviewFormResponse')
                ->get();

            $lockedCount = 0;
            foreach ($overdueAssignments as $assignment) {
                if ($assignment->reviewFormResponse->lock()) {
                    $lockedCount++;
                }
            }

            return response()->json([
                'message' => "Berhasil mengunci {$lockedCount} penugasan yang terlambat.",
                'locked_count' => $lockedCount,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengunci penugasan yang terlambat: '.$e->getMessage()], 500);
        }
    }
}
