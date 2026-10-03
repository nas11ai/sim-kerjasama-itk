<?php

namespace App\Http\Controllers;

use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\AssignReviewer;
use App\Actions\Review\EvaluateSubmissionOutcome;
use App\Actions\Review\OpenReviewThread;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\ReviewCommentAttachment;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\ReviewSummary;
use App\Models\ReviewSummaryAttachment;
use App\Models\SubmissionReviewer;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Queries\Review\EvaluationFormsOverview;
use App\Queries\Review\EvaluationProgress;
use App\Queries\Review\PendingEvaluationCheck;
use App\Queries\Submission\EvaluationDueDate;
use App\Services\EmailNotificationService;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use App\Support\ReviewerWorkload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    protected $emailService;

    public function __construct(EmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    // Enhanced assign reviewers with evaluation forms
    public function assignReviewers(Request $request, FormSubmission $submission)
    {
        $validated = $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'exists:reviewers,id',
            'auto_assign_forms' => 'boolean',
        ]);

        $formPhaseDetail = $submission->getFormPhaseDetail();
        $hasEvaluationForms = $formPhaseDetail && $formPhaseDetail->hasReviewEvaluationForms();
        $autoAssign = $validated['auto_assign_forms'] ?? true;

        // Seluruh permintaan satu transaksi: satu penugasan yang ditolak
        // membatalkan sisanya. Menyimpan sebagian akan menyisakan penugasan
        // yang Operator kira gagal.
        // BR-REV-04 — beban berlebih memberi PERINGATAN, bukan penolakan.
        // Dikumpulkan di luar transaksi supaya ia tetap terbaca walau
        // penugasannya sendiri berhasil: yang perlu Operator ketahui adalah
        // konsekuensinya, bukan kegagalan.
        $peringatanBeban = [];

        DB::transaction(function () use ($validated, $submission, $formPhaseDetail, $hasEvaluationForms, $autoAssign, &$peringatanBeban) {
            foreach ($validated['reviewer_ids'] as $reviewerId) {
                $reviewer = Reviewer::findOrFail($reviewerId);

                $peringatan = ReviewerWorkload::warningFor($submission, $reviewer);

                if ($peringatan !== null) {
                    $peringatanBeban[] = $peringatan;
                }

                // BR-REV-01/02/03/09 ditegakkan di sini. Sebelumnya jalur ini
                // memakai firstOrCreate() polos — nol pemeriksaan konflik
                // kepentingan, nol masa tugas, duplikat lolos diam-diam, dan
                // status ditentukan updateStatusBasedOnReviews() yang bukan
                // BR-REV-01.
                $submissionReviewer = AssignReviewer::run($submission, $reviewer);

                if (!$hasEvaluationForms) {
                    // Tanpa form evaluasi, tidak ada yang ditunggu dari
                    // reviewer ini — BR-FE-06 memperlakukannya sebagai selesai.
                    $submissionReviewer->update(['evaluation_status' => 'not_required']);

                    continue;
                }

                if ($autoAssign) {
                    $this->autoAssignEvaluationForms($submissionReviewer, $formPhaseDetail);
                }

                // REV-ASSIGN-09 menyebut satu notifikasi, dan AssignReviewer
                // sudah mengirimnya. Email Mail custom kedua di sini melanggar
                // AGENTS.md dan jumlahnya bergantung pada ada-tidaknya form.
            }
        });

        $response = back()->with('success', 'Berhasil menugaskan reviewer.');

        // BR-REV-04 — peringatan beban ikut terbawa ke layar. Ia bukan error:
        // penugasannya sudah berhasil, dan Operator yang memutuskan apakah
        // beban itu masih wajar.
        return $peringatanBeban === []
            ? $response
            : $response->with('workloadWarnings', $peringatanBeban);
    }

    // Enhanced remove reviewer to handle evaluation forms
    public function removeReviewer(FormSubmission $submission, Reviewer $reviewer)
    {
        DB::transaction(function () use ($submission, $reviewer) {
            $submissionReviewer = ActiveReviewerAssignment::run($submission, $reviewer->id);

            if (!$submissionReviewer) {
                abort(404, 'Reviewer tidak ditemukan untuk submission ini.');
            }

            // Check if there are submitted evaluation responses
            $hasSubmittedEvaluations = $submissionReviewer->reviewerFormAssignments()
                ->whereHas('reviewFormResponse', function ($query) {
                    $query->where('status', 'submitted');
                })
                ->exists();

            if ($hasSubmittedEvaluations) {
                abort(409, 'Tidak dapat menghapus reviewer dengan respons evaluasi yang telah dikirim.');
            }

            /** @var ReviewerFormAssignment $assignment */

            // Delete evaluation form assignments and draft responses
            foreach ($submissionReviewer->reviewerFormAssignments as $assignment) {
                /** @var ReviewerFormAssignment $assignment */
                /** @var ReviewFormResponse|null $response */
                $response = $assignment->reviewFormResponse;

                if ($response && $response->isDraft()) {
                    $response->reviewFormFieldResponses()->delete();
                    $response->delete();
                }
                $assignment->delete();
            }

            // Delete submission reviewer record
            $submissionReviewer->delete();

            // Delete all ReviewSummary created by this reviewer
            $reviewSummaries = ReviewSummary::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
            ])->get();

            foreach ($reviewSummaries as $summary) {
                $this->deleteReviewSummaryWithComments($summary);
            }

            // Update submission status
            if ($submission->submissionReviewers()->count() === 0) {
                $submission->status->transitionTo(Submitted::class);
            } else {
                EvaluateSubmissionOutcome::run($submission);
            }

            // TAMBAHKAN: Kirim email notifikasi removal
            $this->emailService->notifyReviewerRemoval($submission, $reviewer);
        });

        return back()->with('success', 'Reviewer berhasil dihapus dari submission ini.');
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

        // Operator membuka ulir tanpa penugasan, jadi BR-FE-06 tidak berlaku
        // baginya — ia bukan pihak yang menilai.
        if ($user->can('users.manage')) {
            return $this->openThread($request, $submission, null);
        }

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

    protected function openThread(Request $request, FormSubmission $submission, ?SubmissionReviewer $assignment)
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

    // Enhanced comment adding with evaluation check
    public function addComment(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            'comment_text' => 'required|string|max:2000',
            'parent_comment_id' => 'nullable|exists:review_comments,id',
        ]);

        $user = Auth::user();
        /** @var FormSubmission $submission */
        $submission = $reviewSummary->formSubmission;
        $reviewerId = null;
        $canComment = false;

        // Admin can always comment
        if ($user->can('users.manage')) {
            $canComment = true;
        }
        // Submitter can always comment on their own submission
        elseif ($submission->submitted_by === $user->id) {
            $canComment = true;
        }
        // Check reviewer permissions
        else {
            /** @var Reviewer|null $reviewer */
            $reviewer = Reviewer::where('user_id', $user->id)->first();

            if ($reviewer) {
                $submissionReviewer = ActiveReviewerAssignment::run($submission, $reviewer->id);

                if ($submissionReviewer) {
                    // Check if can participate in discussions
                    if ($submissionReviewer->hasSettledEvaluation()) {
                        $canComment = true;
                        $reviewerId = $reviewer->id;
                    } else {
                        $pendingCount = $submissionReviewer->pending_forms_count;
                        abort(403, "Selesaikan {$pendingCount} formulir evaluasi yang tertunda sebelum berpartisipasi dalam diskusi.");
                    }
                }
            }
        }

        if (!$canComment) {
            abort(403, 'Anda tidak memiliki izin untuk mengomentari review ini');
        }

        DB::transaction(function () use ($request, $reviewSummary, $user, $reviewerId) {
            $comment = ReviewComment::create([
                'review_summary_id' => $reviewSummary->id,
                'parent_comment_id' => $request->parent_comment_id,
                'user_id' => $reviewerId ? null : $user->id,
                'reviewer_id' => $reviewerId,
                'comment_text' => $request->comment_text,
            ]);

            // TAMBAHKAN: Kirim email notifikasi comment
            $this->emailService->notifyReviewComment($comment);
        });

        return back()->with('success', 'Komentar berhasil ditambahkan.');
    }

    // Get available evaluation forms for assignment
    public function getAvailableEvaluationForms(FormSubmission $submission)
    {
        $formPhaseDetail = $submission->getFormPhaseDetail();

        if (!$formPhaseDetail) {
            return response()->json([]);
        }

        $availableForms = $formPhaseDetail->activeReviewEvaluationForms()
            ->get(['id', 'title', 'description', 'is_required', 'order']);

        return response()->json($availableForms);
    }

    // Get evaluation status for submission
    public function getEvaluationStatus(FormSubmission $submission)
    {
        $evaluationStats = EvaluationProgress::run($submission)->toArray();
        $formsSummary = EvaluationFormsOverview::run($submission);
        $discussionsAllowed = !PendingEvaluationCheck::run($submission);

        return response()->json([
            'evaluation_stats' => $evaluationStats,
            'forms_summary' => $formsSummary,
            'discussions_allowed' => $discussionsAllowed,
            'can_proceed_to_discussions' => $discussionsAllowed,
        ]);
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

    // Enhanced available reviewers with evaluation context
    public function getAvailableReviewers(FormSubmission $submission)
    {
        $assignedReviewerIds = SubmissionReviewer::where('form_submission_id', $submission->id)
            ->pluck('reviewer_id')
            ->toArray();

        $availableReviewers = Reviewer::with(['user'])
            ->whereHas('user', function ($query) use ($submission) {
                $query->where('id', '!=', $submission->submitted_by);
            })
            ->whereNotIn('id', $assignedReviewerIds)
            ->get()
            ->map(function ($reviewer) {
                /** @var Reviewer $reviewer */
                return [
                    'id' => $reviewer->id,
                    'name' => $reviewer->user->name,
                    'email' => $reviewer->user->email,
                    'role' => $reviewer->reviewer_type,
                ];
            });

        // Get available evaluation forms
        $evaluationForms = $this->getSubmissionFormPhase($submission)
            ?->activeReviewEvaluationForms()
            ->get(['id', 'title', 'is_required', 'order']) ?? collect();

        return response()->json([
            'reviewers' => $availableReviewers,
            'evaluation_forms' => $evaluationForms,
            'has_evaluation_forms' => $evaluationForms->isNotEmpty(),
        ]);
    }

    // Auto-assign evaluation forms to new reviewers
    protected function autoAssignEvaluationForms(SubmissionReviewer $submissionReviewer, FormPhaseDetail $formPhaseDetail): void
    {
        // Get all required evaluation forms
        $requiredForms = $formPhaseDetail->requiredReviewEvaluationForms()->get();

        foreach ($requiredForms as $form) {
            /** @var ReviewEvaluationForm $form */
            $exists = $submissionReviewer->reviewerFormAssignments()
                ->where('review_evaluation_form_id', $form->id)
                ->exists();

            if ($exists) {
                continue;
            }

            // BR-FE-17 — tenggatnya milik detail yang memiliki form ini, jadi
            // ia dihitung per form. `null` jawaban yang sah: kolomnya nullable
            // dan layar menampilkan "No deadline".
            AssignEvaluationForm::run(
                $submissionReviewer,
                $form->id,
                true, // is_required
                EvaluationDueDate::run($form)
            );
        }
    }

    protected function getSubmissionFormPhase(FormSubmission $submission)
    {
        return FormPhase::whereHas('formPhaseDetails.formAccessControl', function ($query) use ($submission) {
            $query->where('form_id', $submission->form_id);
        })->first();
    }

    // Existing methods with minor updates...
    public function updateReviewStatus(Request $request, ReviewSummary $reviewSummary)
    {
        $request->validate([
            // BR-REV-13 — ReviewSummary hanya `open` atau `resolved`.
            // `closed` tidak pernah ada di domain ini, dan menerimanya
            // menyalakan jalur auto-reject yang dilarang ADR 0006.
            'status' => 'required|in:open,resolved',
        ]);

        $user = Auth::user();
        $canUpdate = false;

        // Admin can always update
        if ($user->can('users.manage')) {
            $canUpdate = true;
        }
        // Check if it's the reviewer's own review
        elseif ($reviewSummary->reviewer_id) {
            /** @var Reviewer|null $reviewer */
            $reviewer = Reviewer::where('user_id', $user->id)->first();

            if ($reviewer && $reviewSummary->reviewer_id === $reviewer->id) {
                // Check if reviewer has completed evaluations (if required)
                $submissionReviewer = ActiveReviewerAssignment::run($reviewSummary->formSubmission, $reviewer->id);

                if ($submissionReviewer) {
                    // Check if evaluations are complete
                    if ($submissionReviewer->hasSettledEvaluation()) {
                        $canUpdate = true;
                    } else {
                        $pendingCount = $submissionReviewer->pending_forms_count;
                        abort(403, "Selesaikan {$pendingCount} formulir evaluasi yang tertunda sebelum memperbarui status review.");
                    }
                }
            }
        }

        if (!$canUpdate) {
            abort(403, 'Anda tidak memiliki izin untuk memperbarui status thread review');
        }

        DB::transaction(function () use ($request, $reviewSummary) {
            $oldStatus = $reviewSummary->status;

            $reviewSummary->update([
                'status' => $request->status,
            ]);

            /** @var FormSubmission $submission */
            $submission = $reviewSummary->formSubmission;

            // BR-REV-05/06 ditegakkan satu tempat. Sebelumnya ada tiga
            // salinan aturan ini, dan dua di antaranya berpindah ke REJECTED
            // secara otomatis — dilarang ADR 0006, BR-REV-07, BR-REV-14.
            EvaluateSubmissionOutcome::run($submission);

            // TAMBAHKAN: Kirim email notifikasi status changed
            $this->emailService->notifyReviewStatusChanged($reviewSummary, $oldStatus);
        });

        $statusText = match ($request->status) {
            'resolved' => 'diselesaikan',
            'open' => 'dibuka kembali',
            default => throw new \InvalidArgumentException('Invalid review status value'),
        };

        return back()->with('success', "Thread review berhasil {$statusText}.");
    }

    public function downloadAttachment(Request $request)
    {
        $filePath = $request->query('path');

        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            abort(404, 'File tidak ditemukan');
        }

        $fullPath = Storage::disk('public')->path($filePath);

        return response()->download($fullPath);
    }

    private function deleteReviewSummaryWithComments(ReviewSummary $summary)
    {
        $comments = ReviewComment::where('review_summary_id', $summary->id)->get();
        /** @var ReviewComment $comment */
        foreach ($comments as $comment) {
            foreach ($comment->attachments as $attachment) {
                /** @var ReviewCommentAttachment $attachment */
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }
            $comment->delete();
        }

        foreach ($summary->attachments as $attachment) {
            /** @var ReviewSummaryAttachment $attachment */
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
        }

        $summary->delete();
    }
}
