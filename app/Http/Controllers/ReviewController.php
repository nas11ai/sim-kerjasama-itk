<?php

namespace App\Http\Controllers;

use App\Actions\Review\AddReviewComment;
use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\AssignReviewers;
use App\Actions\Review\OpenReviewThread;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Queries\Review\ActiveReviewerAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    // BR-REV-01/02/03/04/09 — penegakan dan peringatan beban ada di AssignReviewers.
    public function assignReviewers(Request $request, FormSubmission $submission)
    {
        $validated = $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'integer|exists:reviewers,id',
            'auto_assign_forms' => 'boolean',
        ]);

        $peringatanBeban = AssignReviewers::run(
            $submission,
            array_values($validated['reviewer_ids']),
            $validated['auto_assign_forms'] ?? true,
        );

        $response = back()->with('success', 'Berhasil menugaskan reviewer.');

        return $peringatanBeban === []
            ? $response
            : $response->with('workloadWarnings', $peringatanBeban);
    }

    // Enhanced thread creation with evaluation check
    public function createReviewThread(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'summary_notes' => 'required|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,doc,docx,png,jpg,jpeg,gif|max:10240',
        ]);

        $user = Auth::user();

        // BR-REV-06, BR-REV-15 — ulir hanya dibuka reviewer yang bertugas: ulir open
        // menahan persetujuan, dan hanya reviewer yang berhak menutupnya.
        /** @var Reviewer|null $reviewer */
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        if (!$reviewer) {
            abort(403, 'Anda tidak terdaftar sebagai reviewer');
        }

        $assignment = ActiveReviewerAssignment::run($submission, $reviewer->id);

        if (!$assignment) {
            abort(403, 'Anda tidak ditugaskan sebagai reviewer untuk pengajuan ini.');
        }

        return $this->openThread($request, $submission, $assignment);
    }

    protected function openThread(Request $request, FormSubmission $submission, SubmissionReviewer $assignment)
    {
        OpenReviewThread::run(
            $submission,
            $assignment,
            (string) $request->input('summary_notes'),
            $this->storeAttachments($request, $submission),
        );

        return back()->with('success', 'Thread review berhasil dibuat.');
    }

    /** @return list<string> */
    protected function storeAttachments(Request $request, FormSubmission $submission): array
    {
        if (!$request->hasFile('attachments')) {
            return [];
        }

        $paths = [];

        foreach ($request->file('attachments') as $file) {
            $paths[] = (string) $file->store('review-attachments/'.$submission->id, 'public');
        }

        return $paths;
    }

    // BR-REV-06 — otorisasi, induk satu ulir, dan audit ada di AddReviewComment.
    public function addComment(Request $request, ReviewSummary $reviewSummary)
    {
        $validated = $request->validate([
            'comment_text' => 'required|string|max:2000',
            'parent_comment_id' => 'nullable|integer|exists:review_comments,id',
        ]);

        AddReviewComment::run(
            $reviewSummary,
            $request->user(),
            $validated['comment_text'],
            $request->filled('parent_comment_id') ? $request->integer('parent_comment_id') : null,
        );

        return back()->with('success', 'Komentar berhasil ditambahkan.');
    }

    // Assign specific evaluation forms to reviewer
    public function assignEvaluationFormsToReviewer(Request $request, FormSubmission $submission, Reviewer $reviewer)
    {
        $request->validate([
            'form_assignments' => 'required|array|min:1',
            'form_assignments.*.form_id' => 'required|exists:review_evaluation_forms,id',
            'form_assignments.*.is_required' => 'boolean',
            'form_assignments.*.due_date' => 'nullable|date|after:now',
        ]);

        $submissionReviewer = ActiveReviewerAssignment::run($submission, $reviewer->id);

        if (!$submissionReviewer) {
            return back()->withErrors(['error' => 'Reviewer tidak ditugaskan untuk pengajuan ini.']);
        }

        try {
            DB::beginTransaction();

            foreach ($request->form_assignments as $assignment) {
                AssignEvaluationForm::run(
                    $submissionReviewer,
                    $assignment['form_id'],
                    $assignment['is_required'] ?? true,
                    $assignment['due_date'] ? new \DateTime($assignment['due_date']) : null
                );
            }

            DB::commit();

            return back()->with('success', 'Formulir evaluasi berhasil ditugaskan.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menugaskan formulir evaluasi: '.$e->getMessage()]);
        }
    }
}
