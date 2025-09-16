<?php
// app/Http/Controllers/ReviewController.php (Corrected)

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
use Inertia\Inertia;

class ReviewController extends Controller
{
    // Assign reviewers to submission menggunakan SubmissionReviewer
    public function assignReviewers(Request $request, FormSubmission $submission)
    {
        $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'exists:reviewers,id'
        ]);

        DB::transaction(function () use ($request, $submission) {
            foreach ($request->reviewer_ids as $reviewerId) {
                // Hanya buat record di SubmissionReviewer
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

            // Hapus semua ReviewSummary yang dibuat oleh reviewer ini untuk submission ini
            $reviewSummaries = ReviewSummary::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id
            ])->get();

            foreach ($reviewSummaries as $summary) {
                // Hapus comments dan attachments
                $comments = ReviewComment::where('review_summary_id', $summary->id)->get();
                foreach ($comments as $comment) {
                    // Hapus comment attachments
                    foreach ($comment->attachments as $attachment) {
                        if (Storage::disk('public')->exists($attachment->file_path)) {
                            Storage::disk('public')->delete($attachment->file_path);
                        }
                        $attachment->delete();
                    }
                    $comment->delete();
                }

                // Hapus summary attachments
                foreach ($summary->attachments as $attachment) {
                    if (Storage::disk('public')->exists($attachment->file_path)) {
                        Storage::disk('public')->delete($attachment->file_path);
                    }
                    $attachment->delete();
                }

                $summary->delete();
            }

            // Update submission status jika tidak ada reviewer lagi
            if ($submission->submissionReviewers()->count() === 0) {
                $submission->update(['status' => SubmissionStatus::PENDING]);
            }
        });

        return back()->with('success', 'Reviewer berhasil dihapus dari submission ini.');
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

        if (!$user->is_reviewer) {
            abort(403, 'Unauthorized to create review thread');
        }

        $reviewer = $user->reviewer ?? Reviewer::where('user_id', $user->id)->first();

        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id
        ])->first();

        if (!$submissionReviewer) {
            abort(403, 'Unauthorized to create review thread');
        }

        $reviewerId = $reviewer->id;

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

        // Check if user is an assigned reviewer for this submission
        if ($user->hasRole('Reviewer') || $user->reviewer) {
            $reviewer = $user->reviewer ?? Reviewer::where('user_id', $user->id)->first();
            if ($reviewer) {
                $submissionReviewer = SubmissionReviewer::where([
                    'form_submission_id' => $reviewSummary->form_submission_id,
                    'reviewer_id' => $reviewer->id
                ])->first();

                if ($submissionReviewer) {
                    $reviewerId = $reviewer->id;
                }
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
            'user_id' => $reviewerId ? null : $user->id,
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

    // Update review thread status
    public function updateReviewStatus(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'status' => 'required|in:open,resolved,closed',
            'summary_notes' => 'nullable|string|max:2000'
        ]);

        $user = Auth::user();

        // Check authorization
        $canUpdate = $user->hasRole(['Super Admin', 'Admin']);

        if (!$canUpdate && $reviewSummary->reviewer_id) {
            $reviewer = $user->reviewer ?? Reviewer::where('user_id', $user->id)->first();
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

    // Complete review with final decision (hanya untuk assigned reviewer)
    public function completeReview(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'decision' => 'required|in:approved,needs_revision,rejected',
            'summary_notes' => 'required|string|max:2000'
        ]);

        $user = Auth::user();
        $reviewer = $user->reviewer ?? Reviewer::where('user_id', $user->id)->first();

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

    // Get available reviewers for assignment (exclude already assigned)
    public function getAvailableReviewers(FormSubmission $submission)
    {
        $assignedReviewerIds = SubmissionReviewer::where('form_submission_id', $submission->id)
            ->pluck('reviewer_id')
            ->toArray();

        $availableReviewers = Reviewer::with(['user', 'reviewerRole'])
            ->where('is_active', true)
            ->whereNotIn('id', $assignedReviewerIds)
            // Exclude submission owner from being reviewer
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

    // Private helper to update submission status
    private function updateSubmissionStatus(FormSubmission $submission)
    {
        $reviewSummaries = ReviewSummary::where('form_submission_id', $submission->id)->get();

        if ($reviewSummaries->isEmpty()) {
            $submission->status = SubmissionStatus::PENDING;
        } elseif ($reviewSummaries->where('status', 'closed')->isNotEmpty()) {
            $submission->status = SubmissionStatus::REJECTED;
        } elseif ($reviewSummaries->where('status', 'open')->isNotEmpty()) {
            $submission->status = SubmissionStatus::NEEDS_REVISION;
        } elseif ($reviewSummaries->every(fn($r) => $r->status === 'resolved')) {
            $submission->status = SubmissionStatus::APPROVED;
        } else {
            $submission->status = SubmissionStatus::UNDER_REVIEW;
        }

        $submission->save();
    }
}
