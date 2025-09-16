<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\ReviewSummary;
use App\Models\ReviewComment;
use App\Models\ReviewSummaryAttachment;
use App\Models\ReviewCommentAttachment;
use App\SubmissionStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    // Assign reviewers to submission
    public function assignReviewers(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'exists:reviewers,id'
        ]);

        DB::transaction(function () use ($request, $submission) {
            foreach ($request->reviewer_ids as $reviewerId) {
                SubmissionReviewer::firstOrCreate([
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewerId,
                ]);
            }

            // Update submission status jika ada reviewer yang ditugaskan
            if ($submission->submissionReviewers()->count() > 0) {
                $submission->update(['status' => SubmissionStatus::UNDER_REVIEW]);
            }
        });

        return back()->with('success', 'Reviewer berhasil ditugaskan ke submission ini.');
    }

    // Remove reviewer from submission
    public function removeReviewer(FormSubmission $submission, Reviewer $reviewer)
    {
        DB::transaction(function () use ($submission, $reviewer) {
            // Hapus assignment dari SubmissionReviewer
            SubmissionReviewer::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id
            ])->delete();

            // Hapus semua ReviewSummary yang dibuat oleh reviewer ini
            $reviewSummaries = ReviewSummary::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id
            ])->get();

            foreach ($reviewSummaries as $summary) {
                $this->deleteReviewSummaryWithComments($summary);
            }

            // Update submission status jika tidak ada reviewer lagi
            if ($submission->submissionReviewers()->count() === 0) {
                $submission->update(['status' => SubmissionStatus::PENDING]);
            } else {
                // Update status berdasarkan review yang tersisa
                $this->updateSubmissionStatusBasedOnReviews($submission);
            }
        });

        return back()->with('success', 'Reviewer berhasil dihapus dari submission ini.');
    }

    // BARU: Direct submission status update by reviewer or admin
    public function updateSubmissionStatus(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'status' => 'required|in:approved,needs_revision,rejected',
        ]);

        $user = Auth::user();
        $canUpdate = false;

        // Check if user can update status
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            $canUpdate = true;
        } else {
            // Check if user is assigned reviewer
            $reviewer = Reviewer::where('user_id', $user->id)->first();
            if ($reviewer) {
                $isAssigned = SubmissionReviewer::where([
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewer->id
                ])->exists();

                if ($isAssigned) {
                    $canUpdate = true;
                }
            }
        }

        if (!$canUpdate) {
            abort(403, 'Unauthorized to update submission status');
        }

        // Map frontend status to enum
        $newStatus = match ($request->status) {
            'approved' => SubmissionStatus::APPROVED,
            'needs_revision' => SubmissionStatus::NEEDS_REVISION,
            'rejected' => SubmissionStatus::REJECTED,
        };

        DB::transaction(function () use ($submission, $newStatus, $request, $user) {
            // Update submission status
            $submission->update(['status' => $newStatus]);
        });

        return back()->with('success', "Status submission berhasil diubah menjadi {$newStatus->label()}.");
    }

    // Create review thread (hanya untuk assigned reviewer)
    public function createReviewThread(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'summary_notes' => 'required|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,doc,docx,png,jpg,jpeg,gif|max:10240'
        ]);

        $user = Auth::user();

        // Hanya assigned reviewer yang bisa create thread
        $reviewer = Reviewer::where('user_id', $user->id)->first();
        if (!$reviewer) {
            abort(403, 'You are not registered as a reviewer');
        }

        $isAssigned = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id
        ])->exists();

        if (!$isAssigned) {
            abort(403, 'You are not assigned as reviewer for this submission');
        }

        DB::transaction(function () use ($request, $submission, $reviewer) {
            $reviewSummary = ReviewSummary::create([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
                'status' => 'open',
                'summary_notes' => $request->summary_notes,
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('review-attachments/' . $submission->id, 'public');

                    ReviewSummaryAttachment::create([
                        'review_summary_id' => $reviewSummary->id,
                        'file_path' => $path,
                    ]);
                }
            }

            // Update submission status to needs revision since thread is created
            $submission->update(['status' => SubmissionStatus::NEEDS_REVISION]);
        });

        return back()->with('success', 'Review thread berhasil dibuat.');
    }

    // Add comment to review thread
    public function addComment(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'comment_text' => 'required|string|max:2000',
            'parent_comment_id' => 'nullable|exists:review_comments,id',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|mimes:pdf,doc,docx,png,jpg,jpeg,gif|max:5120'
        ]);

        $user = Auth::user();
        $reviewerId = null;

        // Check authorization: admin, submitter, atau assigned reviewer
        $canComment = false;

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            $canComment = true;
        } elseif ($reviewSummary->formSubmission->submitted_by === $user->id) {
            $canComment = true; // Submitter can comment
        } else {
            // Check if assigned reviewer
            $reviewer = Reviewer::where('user_id', $user->id)->first();
            if ($reviewer) {
                $isAssigned = SubmissionReviewer::where([
                    'form_submission_id' => $reviewSummary->form_submission_id,
                    'reviewer_id' => $reviewer->id
                ])->exists();

                if ($isAssigned) {
                    $canComment = true;
                    $reviewerId = $reviewer->id;
                }
            }
        }

        if (!$canComment) {
            abort(403, 'Unauthorized to comment on this review');
        }

        DB::transaction(function () use ($request, $reviewSummary, $user, $reviewerId) {
            $comment = ReviewComment::create([
                'review_summary_id' => $reviewSummary->id,
                'parent_comment_id' => $request->parent_comment_id,
                'user_id' => $reviewerId ? null : $user->id,
                'reviewer_id' => $reviewerId,
                'comment_text' => $request->comment_text,
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('review-attachments/' . $reviewSummary->form_submission_id, 'public');

                    ReviewCommentAttachment::create([
                        'review_comment_id' => $comment->id,
                        'file_path' => $path,
                    ]);
                }
            }
        });

        return back()->with('success', 'Komentar berhasil ditambahkan.');
    }

    // Update review thread status
    public function updateReviewStatus(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'status' => 'required|in:open,resolved,closed',
            'summary_notes' => 'nullable|string|max:2000'
        ]);

        $user = Auth::user();
        $canUpdate = false;

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            $canUpdate = true;
        } elseif ($reviewSummary->reviewer_id) {
            $reviewer = Reviewer::where('user_id', $user->id)->first();
            if ($reviewer && $reviewSummary->reviewer_id === $reviewer->id) {
                $canUpdate = true;
            }
        }

        if (!$canUpdate) {
            abort(403, 'Unauthorized to update review thread status');
        }

        DB::transaction(function () use ($request, $reviewSummary) {
            $reviewSummary->update([
                'status' => $request->status,
                'summary_notes' => $request->summary_notes ?? $reviewSummary->summary_notes,
            ]);

            // Update overall submission status based on all review threads
            $this->updateSubmissionStatusBasedOnReviews($reviewSummary->formSubmission);
        });

        $statusText = match ($request->status) {
            'resolved' => 'diselesaikan',
            'closed' => 'ditutup',
            'open' => 'dibuka kembali'
        };

        return back()->with('success', "Review thread berhasil {$statusText}.");
    }

    // Get available reviewers for assignment
    public function getAvailableReviewers(FormSubmission $submission)
    {
        $assignedReviewerIds = SubmissionReviewer::where('form_submission_id', $submission->id)
            ->pluck('reviewer_id')
            ->toArray();

        $availableReviewers = Reviewer::with(['user', 'reviewerRole'])
            ->whereHas('user', function ($query) use ($submission) {
                $query->where('id', '!=', $submission->submitted_by);
            })
            ->whereNotIn('id', $assignedReviewerIds)
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

    // Private helpers
    private function deleteReviewSummaryWithComments(ReviewSummary $summary)
    {
        // Delete all comments and their attachments
        $comments = ReviewComment::where('review_summary_id', $summary->id)->get();
        foreach ($comments as $comment) {
            // Delete comment attachments
            foreach ($comment->attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }
            $comment->delete();
        }

        // Delete summary attachments
        foreach ($summary->attachments as $attachment) {
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
        }

        $summary->delete();
    }

    private function updateSubmissionStatusBasedOnReviews(FormSubmission $submission)
    {
        $reviewSummaries = ReviewSummary::where('form_submission_id', $submission->id)->get();

        // Jika tidak ada review threads, status tetap under_review (reviewer belum action)
        if ($reviewSummaries->isEmpty()) {
            return; // Keep current status
        }

        // Jika ada thread yang ditutup (rejected), submission ditolak
        if ($reviewSummaries->where('status', 'closed')->isNotEmpty()) {
            $submission->update(['status' => SubmissionStatus::REJECTED]);
        }
        // Jika ada thread yang masih open, submission needs revision
        elseif ($reviewSummaries->where('status', 'open')->isNotEmpty()) {
            $submission->update(['status' => SubmissionStatus::NEEDS_REVISION]);
        }
        // Jika semua thread resolved, submission approved
        elseif ($reviewSummaries->every(fn($r) => $r->status === 'resolved')) {
            $submission->update(['status' => SubmissionStatus::APPROVED]);
        }
    }
}
