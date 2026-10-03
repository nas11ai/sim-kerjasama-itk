<?php

namespace App\Http\Controllers;

use App\Actions\Review\AddReviewComment;
use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\AssignReviewer;
use App\Actions\Review\OpenReviewThread;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Queries\Review\ActiveReviewerAssignment;
use App\Queries\Submission\EvaluationDueDate;
use App\Support\ReviewerWorkload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
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

        try {
            AddReviewComment::run(
                $reviewSummary,
                $request->user(),
                $validated['comment_text'],
                isset($validated['parent_comment_id']) ? (int) $validated['parent_comment_id'] : null,
            );
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

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
}
