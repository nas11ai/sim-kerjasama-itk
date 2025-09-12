<?php
// app/Http/Controllers/ReviewController.php (Corrected)

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\ReviewComment;
use App\Models\ReviewSummaryAttachment;
use App\Models\ReviewCommentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReviewController extends Controller
{
    // Assign reviewers to submission
    public function assignReviewers(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'exists:reviewers,id'
        ]);

        foreach ($request->reviewer_ids as $reviewerId) {
            // TODO: change this to SubmissionReviewer
            ReviewSummary::firstOrCreate([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewerId,
            ], [
                'status' => 'open',
                'summary_notes' => null,
            ]);
        }

        $submission->status = \App\SubmissionStatus::UNDER_REVIEW;
        $submission->save();

        return back()->with('success', 'Reviewer berhasil ditugaskan');
    }

    // Remove reviewer from submission
    public function removeReviewer(FormSubmission $submission, Reviewer $reviewer)
    {
        ReviewSummary::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id
        ])->delete();

        // Update submission status if no reviewers left
        if ($submission->reviewSummaries()->count() === 0) {
            $submission->status = \App\SubmissionStatus::PENDING;
            $submission->save();
        }

        return back()->with('success', 'Reviewer berhasil dihapus');
    }

    // Create review thread (ReviewSummary)
    public function createReviewThread(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'summary_notes' => 'required|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240' // 10MB
        ]);

        $user = Auth::user();
        $reviewerId = null;

        // Check if user is a reviewer for this submission
        $reviewer = Reviewer::where('user_id', $user->id)->first();
        if ($reviewer) {
            $existingReview = ReviewSummary::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id
            ])->first();

            if ($existingReview) {
                $reviewerId = $reviewer->id;
            }
        }

        // Only allow if user is admin, submission owner, or assigned reviewer
        if (
            !$user->hasRole(['Super Admin', 'Admin']) &&
            $submission->submitted_by !== $user->id &&
            !$reviewerId
        ) {
            abort(403, 'Unauthorized to create review thread');
        }

        $reviewSummary = ReviewSummary::create([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewerId,
            'status' => 'open',
            'summary_notes' => $request->summary_notes,
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('review-attachments', 'public');

                ReviewSummaryAttachment::create([
                    'review_summary_id' => $reviewSummary->id,
                    'file_path' => $path,
                ]);
            }
        }

        return back()->with('success', 'Thread review berhasil dibuat');
    }

    // Add comment to review thread
    public function addComment(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'comment_text' => 'required|string|max:2000',
            'parent_comment_id' => 'nullable|exists:review_comments,id',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|max:5120' // 5MB
        ]);

        $user = Auth::user();
        $reviewerId = null;

        // Determine if user is commenting as a reviewer
        $reviewer = Reviewer::where('user_id', $user->id)->first();
        if ($reviewer) {
            // Check if this reviewer is assigned to this submission
            $isAssignedReviewer = ReviewSummary::where([
                'form_submission_id' => $reviewSummary->form_submission_id,
                'reviewer_id' => $reviewer->id
            ])->exists();

            if ($isAssignedReviewer) {
                $reviewerId = $reviewer->id;
            }
        }

        // Authorization check
        $canComment = $user->hasRole(['Super Admin', 'Admin']) ||
            $reviewSummary->formSubmission->submitted_by === $user->id ||
            $reviewerId;

        if (!$canComment) {
            abort(403, 'Unauthorized to comment on this review');
        }

        $comment = ReviewComment::create([
            'review_summary_id' => $reviewSummary->id,
            'parent_comment_id' => $request->parent_comment_id,
            'user_id' => $reviewerId ? null : $user->id, // If commenting as reviewer, user_id is null
            'reviewer_id' => $reviewerId,
            'comment_text' => $request->comment_text,
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('review-attachments', 'public');

                ReviewCommentAttachment::create([
                    'review_comment_id' => $comment->id,
                    'file_path' => $path,
                ]);
            }
        }

        return back()->with('success', 'Komentar berhasil ditambahkan');
    }

    // Update review thread status (only for assigned reviewers or admin)
    public function updateReviewStatus(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'status' => 'required|in:open,resolved,closed',
            'summary_notes' => 'nullable|string|max:2000'
        ]);

        $user = Auth::user();

        // Check authorization
        $canUpdate = $user->hasRole(['Super Admin', 'Admin']);

        if (!$canUpdate) {
            $reviewer = Reviewer::where('user_id', $user->id)->first();
            if ($reviewer && $reviewSummary->reviewer_id === $reviewer->id) {
                $canUpdate = true;
            }
        }

        if (!$canUpdate) {
            abort(403, 'Unauthorized to update review status');
        }

        $reviewSummary->update([
            'status' => $request->status,
            'summary_notes' => $request->summary_notes ?? $reviewSummary->summary_notes,
        ]);

        // Update submission status based on all review summaries
        $this->updateSubmissionStatus($reviewSummary->formSubmission);

        $statusText = match ($request->status) {
            'resolved' => 'diselesaikan',
            'closed' => 'ditutup',
            'open' => 'dibuka kembali'
        };

        return back()->with('success', "Review berhasil {$statusText}");
    }

    // Complete review with final decision (only for assigned reviewers)
    public function completeReview(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'decision' => 'required|in:approved,needs_revision,rejected',
            'summary_notes' => 'required|string|max:2000'
        ]);

        $user = Auth::user();
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        // Only assigned reviewer can complete their own review
        if (!$reviewer || $reviewSummary->reviewer_id !== $reviewer->id) {
            abort(403, 'Unauthorized to complete this review');
        }

        $status = match ($request->decision) {
            'approved' => 'resolved',
            'needs_revision' => 'open',
            'rejected' => 'closed'
        };

        $reviewSummary->update([
            'status' => $status,
            'summary_notes' => $request->summary_notes,
        ]);

        // Add system comment about the decision
        ReviewComment::create([
            'review_summary_id' => $reviewSummary->id,
            'reviewer_id' => $reviewer->id,
            'comment_text' => "Review diselesaikan dengan keputusan: " . match ($request->decision) {
                'approved' => 'Disetujui',
                'needs_revision' => 'Perlu Revisi',
                'rejected' => 'Ditolak'
            },
        ]);

        $this->updateSubmissionStatus($reviewSummary->formSubmission);

        return back()->with('success', 'Review berhasil diselesaikan');
    }

    // Get available reviewers for assignment (exclude those already assigned)
    public function getAvailableReviewers(FormSubmission $submission)
    {
        $assignedReviewerIds = ReviewSummary::where('form_submission_id', $submission->id)
            ->pluck('reviewer_id')
            ->filter()
            ->toArray();

        $availableReviewers = Reviewer::with(['user', 'reviewerRole'])
            ->where('is_active', true)
            ->whereNotIn('id', $assignedReviewerIds)
            // Exclude submission owner from being reviewer of their own submission
            ->whereHas('user', function ($query) use ($submission) {
                $query->where('id', '!=', $submission->submitted_by);
            })
            ->get()
            ->map(function ($reviewer) {
                return [
                    'id' => $reviewer->id,
                    'name' => $reviewer->user->name,
                    'email' => $reviewer->user->email,
                    'role' => $reviewer->reviewerRole->name,
                ];
            });

        return response()->json($availableReviewers);
    }

    // Download attachment
    public function downloadAttachment(Request $request)
    {
        $filePath = $request->query('path');

        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            abort(404, 'File tidak ditemukan');
        }

        $fullPath = Storage::disk('public')->path($filePath);
        return response()->download($fullPath);
    }

    // Check if current user can review a specific submission
    public function canUserReviewSubmission(FormSubmission $submission): bool
    {
        $user = Auth::user();

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        $reviewer = Reviewer::where('user_id', $user->id)->first();
        if (!$reviewer) {
            return false;
        }

        return ReviewSummary::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id
        ])->exists();
    }

    // Private helper to update submission status
    private function updateSubmissionStatus(FormSubmission $submission)
    {
        $reviewSummaries = ReviewSummary::where('form_submission_id', $submission->id)->get();

        if ($reviewSummaries->isEmpty()) {
            $submission->status = \App\SubmissionStatus::PENDING;
        } elseif ($reviewSummaries->where('status', 'closed')->isNotEmpty()) {
            $submission->status = \App\SubmissionStatus::REJECTED;
        } elseif ($reviewSummaries->where('status', 'open')->isNotEmpty()) {
            $submission->status = \App\SubmissionStatus::NEEDS_REVISION;
        } elseif ($reviewSummaries->every(fn($r) => $r->status === 'resolved')) {
            $submission->status = \App\SubmissionStatus::APPROVED;
        } else {
            $submission->status = \App\SubmissionStatus::UNDER_REVIEW;
        }

        $submission->save();
    }
}
