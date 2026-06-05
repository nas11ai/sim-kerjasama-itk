<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\ReviewSummary;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReviewFormResponseController extends Controller
{
    /**
     * Get default due date for evaluation
     */
    protected function getDefaultDueDate(FormSubmission $submission): ?\DateTime
    {
        $submissionPeriod = SubmissionPeriod::whereHas(
            'submissionPeriodPhases.formPhase.formPhaseDetails.formAccessControl',
            function ($query) use ($submission) {
                $query->where('form_id', $submission->form_id);
            }
        )->first();

        if (!$submissionPeriod) {
            // Default to 7 days from now
            return new \DateTime('+7 days');
        }

        // Get the latest submission date
        $latestDate = $submissionPeriod->submissionDates()
            ->orderBy('datetime', 'desc')
            ->first();

        if ($latestDate && $latestDate->datetime) {
            // Pastikan dikonversi ke objek DateTime
            return new \DateTime($latestDate->datetime);
        }

        return new \DateTime('+7 days');
    }

    public function showOrCreate(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|exists:form_submissions,id',
            'review_evaluation_form_id' => 'required|exists:review_evaluation_forms,id',
        ]);

        $user = Auth::user();
        $submission = FormSubmission::findOrFail($validated['submission_id']);

        // Get reviewer
        $reviewer = $user->reviewers()->first();
        if (!$reviewer) {
            abort(403, 'Anda tidak terdaftar sebagai reviewer.');
        }

        // Check if assigned to this submission
        $submissionReviewer = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ])->first();

        if (!$submissionReviewer) {
            abort(403, 'Anda tidak ditugaskan sebagai reviewer untuk pengajuan ini.');
        }

        // Verify form belongs to correct form phase
        $formPhaseDetail = $submission->getFormPhaseDetail();
        $evaluationForm = ReviewEvaluationForm::findOrFail($validated['review_evaluation_form_id']);

        if (!$formPhaseDetail || $evaluationForm->form_phase_detail_id !== $formPhaseDetail->id) {
            abort(403, 'Formulir evaluasi ini tidak tersedia untuk pengajuan ini.');
        }

        // Find or create assignment
        $assignment = ReviewerFormAssignment::firstOrCreate(
            [
                'submission_reviewer_id' => $submissionReviewer->id,
                'review_evaluation_form_id' => $evaluationForm->id,
            ],
            [
                'is_required' => $evaluationForm->is_required,
                'assigned_at' => now(),
                'due_date' => $this->getDefaultDueDate($submission),
                'is_active' => true,
            ]
        );

        // Update evaluation status
        $submissionReviewer->updateEvaluationStatus();

        // Redirect to show page
        return redirect()->route('reviewer.evaluation-form.show', $assignment->id);
    }

    public function show(ReviewerFormAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $assignment->load([
            'reviewEvaluationForm' => function ($query) {
                $query->with([
                    'reviewFormFields' => function ($q) {
                        $q->ordered()->with([
                            'fieldType:id,name',
                            'reviewFormFieldOptions' => function ($opt) {
                                $opt->ordered();
                            },
                        ]);
                    },
                ]);
            },
            'submissionReviewer.formSubmission.form:id,title',
            'submissionReviewer.formSubmission.submittedBy:id,name',
            'reviewFormResponse.reviewFormFieldResponses',
        ]);

        // Get or create response
        $response = $assignment->reviewFormResponse ?? ReviewFormResponse::create([
            'reviewer_form_assignment_id' => $assignment->id,
            'status' => 'draft',
        ]);

        // Map existing responses
        $existingResponses = $response->reviewFormFieldResponses
            ->mapWithKeys(function ($fieldResponse) {
                return [$fieldResponse->review_form_field_id => $fieldResponse->value];
            });

        return Inertia::render('Reviewer/EvaluationForm/Show', [
            'assignment' => $assignment,
            'response' => $response,
            'existingResponses' => $existingResponses,
            'canEdit' => $response->canBeEdited(),
            'canSubmit' => $response->canBeSubmitted(),
            'completionPercentage' => $response->getCompletionPercentage(),
        ]);
    }

    public function saveDraft(Request $request, ReviewerFormAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $response = $assignment->reviewFormResponse ?? ReviewFormResponse::create([
            'reviewer_form_assignment_id' => $assignment->id,
            'status' => 'draft',
        ]);

        if (!$response->canBeEdited()) {
            return back()->withErrors(['error' => 'Formulir ini tidak dapat diedit lagi.']);
        }

        $validated = $request->validate([
            'responses' => 'required|array',
            'responses.*' => 'nullable|string',
            'final_notes' => 'nullable|string|max:2000',
        ]);

        try {
            DB::beginTransaction();

            // Save field responses
            foreach ($validated['responses'] as $fieldId => $value) {
                $response->saveFieldResponse((int) $fieldId, $value);
            }

            // Update final notes
            $response->update([
                'final_notes' => $validated['final_notes'] ?? null,
            ]);

            DB::commit();

            return back()->with('success', 'Draft berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menyimpan draft: '.$e->getMessage()]);
        }
    }

    public function submit(Request $request, ReviewerFormAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $response = $assignment->reviewFormResponse;

        if (!$response instanceof ReviewFormResponse) {
            return back()->withErrors(['error' => 'Tidak ditemukan draft respons. Silakan simpan draft terlebih dahulu.']);
        }

        if (!$response->canBeSubmitted()) {
            return back()->withErrors(['error' => 'Formulir tidak dapat dikirim. Harap lengkapi semua bidang yang diperlukan.']);
        }

        $validated = $request->validate([
            'responses' => 'required|array',
            'responses.*' => 'nullable|string',
            'final_notes' => 'nullable|string|max:2000',
        ]);

        try {
            DB::beginTransaction();

            // Save final responses
            foreach ($validated['responses'] as $fieldId => $value) {
                $response->saveFieldResponse((int) $fieldId, $value);
            }

            // Update final notes
            $response->update([
                'final_notes' => $validated['final_notes'] ?? null,
            ]);

            // Validate all required fields are completed
            $errors = $this->validateRequiredFields($response);
            if (!empty($errors)) {
                throw ValidationException::withMessages(['responses' => $errors]);
            }

            // Submit the response
            if (!$response->submit()) {
                throw new \Exception('Gagal mengirim respons formulir.');
            }

            DB::commit();

            return redirect()->route('reviewer.assignments.index')
                ->with('success', 'Formulir evaluasi berhasil dikirim.');

        } catch (ValidationException $e) {
            DB::rollback();
            throw $e;
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal mengirim formulir: '.$e->getMessage()]);
        }
    }

    public function showSubmitted(ReviewerFormAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        if (!$assignment->reviewFormResponse || !$assignment->reviewFormResponse->isSubmitted()) {
            return redirect()->route('reviewer.evaluation-form.show', $assignment);
        }

        $assignment->load([
            'reviewEvaluationForm.reviewFormFields' => function ($query) {
                $query->ordered()->with([
                    'fieldType:id,name',
                    'reviewFormFieldOptions' => function ($q) {
                        $q->ordered();
                    },
                ]);
            },
            'submissionReviewer.formSubmission.form:id,title',
            'submissionReviewer.formSubmission.submittedBy:id,name',
            'reviewFormResponse.reviewFormFieldResponses',
        ]);

        $response = $assignment->reviewFormResponse;

        // Map responses for display
        $formattedResponses = $response->reviewFormFieldResponses
            ->mapWithKeys(function ($fieldResponse) {
                return [
                    $fieldResponse->review_form_field_id => [
                        'value' => $fieldResponse->value,
                        'formatted_value' => $fieldResponse->formatted_value,
                    ],
                ];
            });

        return Inertia::render('Reviewer/EvaluationForm/Submitted', [
            'assignment' => $assignment,
            'response' => $response,
            'formattedResponses' => $formattedResponses,
        ]);
    }

    public function downloadSummary(ReviewerFormAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $response = $assignment->reviewFormResponse;
        if (!$response instanceof ReviewFormResponse || !$response->isSubmitted()) {
            return back()->withErrors(['error' => 'Tidak ditemukan respons yang telah dikirim.']);
        }

        $assignment->load([
            'reviewEvaluationForm.reviewFormFields' => function ($query) {
                $query->ordered()->with('reviewFormFieldOptions');
            },
            'submissionReviewer.formSubmission.form:id,title',
            'submissionReviewer.formSubmission.submittedBy:id,name',
            'reviewFormResponse.reviewFormFieldResponses',
        ]);

        $summaryContent = $this->generateSummaryContent($assignment, $response);

        return response()->streamDownload(function () use ($summaryContent) {
            echo $summaryContent;
        }, "evaluation-summary-{$assignment->id}.txt", [
            'Content-Type' => 'text/plain',
        ]);
    }

    protected function authorizeAssignment(ReviewerFormAssignment $assignment): void
    {
        $user = Auth::user();

        // Admin can view any assignment
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return;
        }

        // Check if user is the assigned reviewer
        $reviewer = $user->reviewers()->first();
        if (!$reviewer || $assignment->submissionReviewer->reviewer_id !== $reviewer->id) {
            abort(403, 'Akses tidak sah ke formulir evaluasi ini.');
        }
    }

    protected function validateRequiredFields(ReviewFormResponse $response): array
    {
        $errors = [];
        $evaluationForm = $response->reviewerFormAssignment->reviewEvaluationForm;

        foreach ($evaluationForm->reviewFormFields()->required()->get() as $field) {
            $fieldResponse = $response->reviewFormFieldResponses()
                ->where('review_form_field_id', $field->id)
                ->first();

            if (!$fieldResponse || !$fieldResponse->hasValue()) {
                $errors[] = "Field '{$field->label}' wajib diisi.";
            } else {
                // Field-specific validation
                $fieldErrors = $fieldResponse->validate();
                $errors = array_merge($errors, $fieldErrors);
            }
        }

        return $errors;
    }

    protected function createOrUpdateReviewSummary(ReviewerFormAssignment $assignment)
    {
        $submissionReviewer = $assignment->submissionReviewer;

        // Check if all required evaluations are completed
        if (!$submissionReviewer->hasAllRequiredFormsCompleted()) {
            return;
        }

        // Create or update review summary
        ReviewSummary::createFromEvaluationResponses(
            $submissionReviewer->form_submission_id,
            $submissionReviewer->reviewer_id,
            'open' // Start with open status for discussion
        );
    }

    protected function generateSummaryContent(ReviewerFormAssignment $assignment, ReviewFormResponse $response): string
    {
        $submission = $assignment->submissionReviewer->formSubmission;
        $form = $assignment->reviewEvaluationForm;
        $reviewer = $assignment->submissionReviewer->reviewer;

        $content = "RANGKUMAN EVALUASI\n";
        $content .= str_repeat('=', 50)."\n\n";

        $content .= "Pengajuan: {$submission->form->title}\n";
        $content .= "Dikirim oleh: {$submission->submittedBy->name}\n";
        $content .= "Reviewer: {$reviewer->user->name} ({$reviewer->reviewerRole->name})\n";
        $content .= "Formulir Evaluasi: {$form->title}\n";
        $content .= "Diselesaikan pada: {$response->submitted_at->format('d M Y H:i')}\n\n";

        $content .= str_repeat('-', 50)."\n\n";

        foreach ($form->reviewFormFields()->ordered()->get() as $field) {
            $fieldResponse = $response->reviewFormFieldResponses()
                ->where('review_form_field_id', $field->id)
                ->first();

            $content .= "{$field->label}\n";
            if ($field->description) {
                $content .= "Deskripsi: {$field->description}\n";
            }

            if ($fieldResponse && $fieldResponse->hasValue()) {
                $content .= "Response: {$fieldResponse->formatted_value}\n";
            } else {
                $content .= "Response: [No response]\n";
            }
            $content .= "\n";
        }

        if ($response->final_notes) {
            $content .= str_repeat('-', 50)."\n";
            $content .= "Catatan Tambahan:\n";
            $content .= $response->final_notes."\n\n";
        }

        $content .= str_repeat('=', 50)."\n";
        $content .= 'Generated on: '.now()->format('d M Y H:i:s')."\n";

        return $content;
    }

    public function getProgress(ReviewerFormAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $response = $assignment->reviewFormResponse;

        if (!$response) {
            return response()->json([
                'status' => 'not_started',
                'completion_percentage' => 0,
                'can_submit' => false,
                'is_overdue' => $assignment->isOverdue(),
                'days_until_due' => $assignment->getDaysUntilDue(),
            ]);
        }

        return response()->json([
            'status' => $response->status,
            'completion_percentage' => $response->getCompletionPercentage(),
            'can_submit' => $response->canBeSubmitted(),
            'can_edit' => $response->canBeEdited(),
            'is_overdue' => $assignment->isOverdue(),
            'days_until_due' => $assignment->getDaysUntilDue(),
            'submitted_at' => $response->submitted_at,
        ]);
    }
}
